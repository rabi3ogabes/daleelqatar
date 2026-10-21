<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessStatus;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequest;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessImage;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BusinessManager;
use App\Services\ListingWorkflow;
use App\Services\MediaService;
use App\Services\SlugGenerator;
use App\Services\VerificationService;
use App\Support\AdminNavigation;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessController extends Controller
{
    public const SORTS = ['newest', 'oldest', 'name', 'submitted', 'views', 'rating'];

    public function __construct(
        private readonly BusinessManager $manager,
        private readonly ListingWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, NavigationData $nav): View
    {
        $businesses = $this->filtered($request)
            ->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id', 'owner:id,name,email'])
            ->paginate(20)->withQueryString();

        $statusCounts = Business::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'statusCounts' => $statusCounts,
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
        ]);
    }

    public function create(NavigationData $nav): View
    {
        return view('admin.businesses.create', [
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
            'areaOptions' => $nav->areaOptions(),
        ]);
    }

    public function store(BusinessRequest $request): RedirectResponse
    {
        $extra = $request->validate([
            'owner_email' => ['nullable', 'email', Rule::exists('users', 'email')->whereNull('deleted_at')],
            'publish' => ['nullable', 'boolean'],
        ]);

        $owner = ! empty($extra['owner_email']) ? User::query()->where('email', mb_strtolower($extra['owner_email']))->first() : null;
        $business = $this->manager->create($request->user(), $request->businessData(), $owner);

        if ($request->hasFile('logo')) {
            $this->manager->setLogo($business, $request->file('logo'));
        }
        if ($request->hasFile('cover')) {
            $this->manager->setCover($business, $request->file('cover'));
        }
        if (! empty($extra['publish'])) {
            $this->workflow->approve($business, $request->user(), __('admin.businesses.published_on_create'));
        }

        $this->flush();

        return redirect()->route('admin.businesses.show', $business->id)->with('success', __('admin.businesses.created'));
    }

    public function show(Business $business): View
    {
        $business->load([
            'category.parent', 'categories', 'municipality', 'area', 'owner', 'approver:id,name', 'images', 'services', 'openingHours', 'socialLinks',
            'statusChanges.user:id,name',
            'claimRequests' => fn ($q) => $q->with('user:id,name,email')->latest(),
            'verificationRequests' => fn ($q) => $q->with('user:id,name')->latest(),
            'reports' => fn ($q) => $q->latest()->limit(10),
            'offers' => fn ($q) => $q->latest()->limit(10),
        ]);

        $audit = AuditLog::query()->with('user:id,name')
            ->where('auditable_type', $business->getMorphClass())->where('auditable_id', $business->id)
            ->latest('created_at')->latest('id')->limit(15)->get();

        return view('admin.businesses.show', [
            'business' => $business,
            'audit' => $audit,
            'missing' => $this->workflow->missingRequirements($business),
            'transitions' => $business->status->transitions(),
        ]);
    }

    public function edit(Business $business, NavigationData $nav): View
    {
        $business->load(['openingHours', 'services', 'socialLinks', 'categories']);

        return view('admin.businesses.edit', [
            'business' => $business,
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
            'areaOptions' => $nav->areaOptions(),
        ]);
    }

    public function update(BusinessRequest $request, Business $business): RedirectResponse
    {
        $extra = $request->validate([
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('businesses', 'slug')->ignore($business->id)],
        ]);

        $this->manager->update($business, $request->user(), $request->businessData() + ['slug' => $extra['slug']]);

        if ($request->hasFile('logo')) {
            $this->manager->setLogo($business, $request->file('logo'));
        }
        if ($request->hasFile('cover')) {
            $this->manager->setCover($business, $request->file('cover'));
        }

        $this->flush();

        return redirect()->route('admin.businesses.show', $business->id)->with('success', __('common.updated'));
    }

    public function destroy(Request $request, Business $business): RedirectResponse
    {
        $this->manager->delete($business, $request->user());
        $this->flush();

        return redirect()->route('admin.businesses.index')->with('success', __('admin.businesses.deleted'));
    }

    public function restoreDeleted(Request $request, Business $business): RedirectResponse
    {
        abort_unless($business->trashed(), 404);
        $business->restore();
        $this->audit->log('business.restored', $business);
        $this->flush();

        return back()->with('success', __('admin.businesses.restored'));
    }

    /** Single moderation endpoint: approve / reject / suspend / restore / archive / unarchive / reopen. */
    public function status(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'suspend', 'restore', 'archive', 'unarchive', 'reopen'])],
            'reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($request->input('action'), ['reject', 'suspend'], true))],
        ]);
        $actor = $request->user();
        $reason = $data['reason'] ?? null;

        // Each action is only meaningful from specific states (e.g. "restore" only reinstates a suspended listing).
        $allowedFrom = [
            'approve' => [BusinessStatus::Draft, BusinessStatus::Pending],
            'reject' => [BusinessStatus::Pending],
            'suspend' => [BusinessStatus::Approved],
            'restore' => [BusinessStatus::Suspended],
            'archive' => [BusinessStatus::Draft, BusinessStatus::Pending, BusinessStatus::Approved, BusinessStatus::Rejected, BusinessStatus::Suspended],
            'unarchive' => [BusinessStatus::Archived],
            'reopen' => [BusinessStatus::Approved],
        ];
        if (! in_array($business->status, $allowedFrom[$data['action']], true)) {
            return back()->with('error', __('workflow.invalid_transition', ['from' => $business->status->label(), 'to' => __('admin.businesses.actions.'.$data['action'])]));
        }

        try {
            match ($data['action']) {
                'approve' => $this->workflow->approve($business, $actor, $reason),
                'reject' => $this->workflow->reject($business, $actor, (string) $reason),
                'suspend' => $this->workflow->suspend($business, $actor, (string) $reason),
                'restore' => $this->workflow->restore($business, $actor, $reason),
                'archive' => $this->workflow->archive($business, $actor, $reason),
                'unarchive' => $this->workflow->unarchive($business, $actor),
                'reopen' => $this->workflow->requireReapproval($business, $actor),
            };
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->flush();

        return back()->with('success', __('admin.businesses.status_updated', ['status' => $business->status->label()]));
    }

    public function feature(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'featured' => ['required', 'boolean'],
            'featured_until' => ['nullable', 'date', 'after:today'],
        ]);

        $old = ['is_featured' => $business->is_featured, 'featured_until' => $business->featured_until?->toDateString()];
        $business->forceFill([
            'is_featured' => (bool) $data['featured'],
            'featured_until' => $data['featured'] ? ($data['featured_until'] ?? null) : null,
        ])->save();
        $this->audit->log($data['featured'] ? 'business.featured' : 'business.unfeatured', $business, $old,
            ['is_featured' => $business->is_featured, 'featured_until' => $business->featured_until?->toDateString()]);
        $this->flush();

        return back()->with('success', $data['featured'] ? __('admin.businesses.featured_on') : __('admin.businesses.featured_off'));
    }

    public function verify(Request $request, Business $business, VerificationService $verification): RedirectResponse
    {
        $data = $request->validate([
            'verified' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['verified']) {
            $verification->verify($business, $request->user());
        } else {
            $verification->revoke($business, $request->user(), $data['note'] ?? null);
        }
        $this->flush();

        return back()->with('success', $data['verified'] ? __('admin.businesses.verified_on') : __('admin.businesses.verified_off'));
    }

    public function owner(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'owner_email' => ['nullable', 'email', Rule::exists('users', 'email')->whereNull('deleted_at')],
        ]);

        $old = $business->owner_id;
        $owner = ! empty($data['owner_email']) ? User::query()->where('email', mb_strtolower($data['owner_email']))->firstOrFail() : null;
        $business->forceFill(['owner_id' => $owner?->id])->save();
        if ($owner && ! $owner->hasRole(Permissions::BUSINESS_OWNER)) {
            $owner->assignRole(Permissions::BUSINESS_OWNER);
        }
        $this->audit->log('business.owner.changed', $business, ['owner_id' => $old], ['owner_id' => $owner?->id]);

        return back()->with('success', $owner ? __('admin.businesses.owner_assigned', ['name' => $owner->name]) : __('admin.businesses.owner_removed'));
    }

    public function logo(Request $request, Business $business): RedirectResponse
    {
        $request->validate(['logo' => ['required', ...$this->imageRules()]]);
        $this->manager->setLogo($business, $request->file('logo'));

        return back()->with('success', __('common.updated'));
    }

    public function cover(Request $request, Business $business): RedirectResponse
    {
        $request->validate(['cover' => ['required', ...$this->imageRules()]]);
        $this->manager->setCover($business, $request->file('cover'));

        return back()->with('success', __('common.updated'));
    }

    public function gallery(Request $request, Business $business): RedirectResponse
    {
        $max = (int) setting('uploads.max_gallery_images', 12);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.$max],
            'images.*' => $this->imageRules(),
        ]);

        try {
            $this->manager->addGalleryImages($business, $request->file('images'));
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('business_form.images_uploaded'));
    }

    public function destroyImage(Business $business, BusinessImage $image): RedirectResponse
    {
        abort_unless($image->business_id === $business->id, 404);
        $image->setRelation('business', $business);
        $this->manager->deleteImage($image);

        return back()->with('success', __('business_form.image_deleted'));
    }

    /** Streams the filtered listing table as CSV (UTF-8 with BOM for Excel). Private documents are never exported. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id', 'owner:id,email']);
        $count = (clone $query)->count();
        $this->audit->log('business.exported', null, [], ['rows' => $count], ['filters' => $request->only(['q', 'status', 'category', 'municipality', 'verified', 'featured', 'demo', 'trashed'])]);

        $filename = 'daleel-qatar-businesses-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'uuid', 'name_ar', 'name_en', 'slug', 'status', 'category_ar', 'category_en', 'municipality', 'area', 'phone', 'whatsapp', 'email', 'website', 'owner_email', 'verified', 'featured', 'demo', 'rating', 'reviews', 'views', 'favorites', 'created_at', 'published_at']);
            $query->reorder()->chunkById(500, function ($chunk) use ($out) {
                foreach ($chunk as $b) {
                    fputcsv($out, [
                        $b->id, $b->uuid, $b->name_ar, $b->name_en, $b->slug, $b->status->value,
                        $b->category?->name_ar, $b->category?->name_en, $b->municipality?->name_en, $b->area?->name_en,
                        $b->phone, $b->whatsapp, $b->email, $b->website, $b->owner?->email,
                        $b->is_verified ? 'yes' : 'no', $b->isFeaturedNow() ? 'yes' : 'no', $b->is_demo ? 'yes' : 'no',
                        $b->rating_avg, $b->reviews_count, $b->views_count, $b->favorites_count,
                        $b->created_at?->toDateTimeString(), $b->published_at?->toDateTimeString(),
                    ]);
                }
            }, 'businesses.id', 'id');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        $query = Business::query()->select('businesses.*');

        if ($request->query('trashed') === 'only') {
            $query->onlyTrashed();
        }
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
            $query->where(fn (Builder $w) => $w->whereRaw('lower(businesses.name_ar) LIKE ?', [$like])
                ->orWhereRaw('lower(businesses.name_en) LIKE ?', [$like])
                ->orWhereRaw('businesses.slug LIKE ?', [$like])
                ->orWhereRaw('lower(coalesce(businesses.email, \'\')) LIKE ?', [$like])
                ->orWhereRaw('coalesce(businesses.phone, \'\') LIKE ?', [$like]));
        }
        if ($status = BusinessStatus::tryFrom((string) $request->query('status'))) {
            $query->where('businesses.status', $status);
        }
        if ($categoryId = $request->integer('category')) {
            $category = Category::query()->find($categoryId);
            if ($category) {
                $query->inCategory($category);
            }
        }
        if ($municipality = $request->integer('municipality')) {
            $query->where('businesses.municipality_id', $municipality);
        }
        foreach (['verified' => 'is_verified', 'featured' => 'is_featured', 'demo' => 'is_demo'] as $param => $column) {
            if (in_array($request->query($param), ['1', '0'], true)) {
                $query->where('businesses.'.$column, $request->query($param) === '1');
            }
        }
        if ($request->query('owner') === 'none') {
            $query->whereNull('businesses.owner_id');
        }

        match (in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest') {
            'oldest' => $query->orderBy('businesses.created_at'),
            'name' => $query->orderBy('businesses.'.Business::localizedColumn('name')),
            'submitted' => $query->orderByRaw('businesses.submitted_at DESC NULLS LAST'),
            'views' => $query->orderByDesc('businesses.views_count'),
            'rating' => $query->orderByRaw('businesses.rating_avg DESC NULLS LAST'),
            default => $query->orderByDesc('businesses.created_at'),
        };

        return $query->orderByDesc('businesses.id');
    }

    private function imageRules(): array
    {
        return ['image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120), 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'];
    }

    private function flush(): void
    {
        Directory::flushCounts();
        AdminNavigation::flush();
        Cache::forget('sitemap.xml');
    }
}
