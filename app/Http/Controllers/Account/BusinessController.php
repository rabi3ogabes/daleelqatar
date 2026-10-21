<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\BusinessEventType;
use App\Enums\BusinessStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequest;
use App\Models\Business;
use App\Models\BusinessEvent;
use App\Services\BusinessManager;
use App\Services\ListingWorkflow;
use App\Services\SettingsService;
use App\Support\Directory;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request, ListingWorkflow $workflow): View
    {
        $businesses = $request->user()->businesses()
            ->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id', 'images' => fn ($q) => $q->limit(1)])
            ->latest('updated_at')->paginate(12);

        return view('account.businesses.index', [
            'businesses' => $businesses,
            'completeness' => $businesses->getCollection()->mapWithKeys(fn (Business $b) => [$b->id => $this->completeness($b, $workflow)]),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Business::class);

        return view('account.businesses.create');
    }

    public function store(BusinessRequest $request, BusinessManager $manager): RedirectResponse
    {
        $this->authorize('create', Business::class);

        $business = $manager->create($request->user(), $request->businessData());

        if ($request->hasFile('logo')) {
            $manager->setLogo($business, $request->file('logo'));
        }
        if ($request->hasFile('cover')) {
            $manager->setCover($business, $request->file('cover'));
        }
        Directory::flushCounts();

        return redirect()->route('account.businesses.show', $business->uuid)->with('success', __('account.businesses.created'));
    }

    public function show(Request $request, Business $business, ListingWorkflow $workflow): View
    {
        $this->authorizeOwner($request, $business);

        $business->load(['category', 'municipality', 'area', 'images', 'statusChanges.user:id,name',
            'verificationRequests' => fn ($q) => $q->latest()->limit(1)]);

        $since = CarbonImmutable::now()->subDays(29)->startOfDay();
        $events = BusinessEvent::query()->where('business_id', $business->id)->where('created_at', '>=', $since)
            ->selectRaw('type, DATE(created_at) AS day, COUNT(*) AS total')->groupBy('type', 'day')->get();
        $viewsByDay = $events->where('type', BusinessEventType::View)->pluck('total', 'day');

        $labels = [];
        $values = [];
        for ($d = $since; $d->lte(CarbonImmutable::now()); $d = $d->addDay()) {
            $labels[] = $d->locale(app()->getLocale())->isoFormat('D MMM');
            $values[] = (int) ($viewsByDay[$d->toDateString()] ?? 0);
        }

        $interactions = collect(BusinessEventType::trackable())->mapWithKeys(fn (BusinessEventType $t) => [
            $t->value => (int) $events->where('type', $t)->sum('total'),
        ]);

        return view('account.businesses.show', [
            'business' => $business,
            'missing' => $workflow->missingRequirements($business),
            'completeness' => $this->completeness($business, $workflow),
            'chartLabels' => $labels,
            'chartValues' => $values,
            'views30' => array_sum($values),
            'interactions' => $interactions,
            'pendingVerification' => $business->verificationRequests->first()?->status === RequestStatus::Pending,
        ]);
    }

    public function edit(Request $request, Business $business, SettingsService $settings): View
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('update', $business);

        $business->load(['categories:id', 'openingHours', 'services', 'socialLinks', 'images']);

        return view('account.businesses.edit', [
            'business' => $business,
            'maxGallery' => $settings->int('uploads.max_gallery_images', 12),
        ]);
    }

    public function update(BusinessRequest $request, Business $business, BusinessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('update', $business);

        $manager->update($business, $request->user(), $request->businessData());

        if ($request->hasFile('logo')) {
            $manager->setLogo($business, $request->file('logo'));
        }
        if ($request->hasFile('cover')) {
            $manager->setCover($business, $request->file('cover'));
        }
        Directory::flushCounts();

        if ($request->input('after') === 'submit' && $request->user()->can('submit', $business->refresh())) {
            return $this->submit($request, $business, app(ListingWorkflow::class));
        }

        return redirect()->route('account.businesses.edit', $business->uuid)->with('success', __('account.businesses.updated'));
    }

    public function submit(Request $request, Business $business, ListingWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('submit', $business);

        $business = $workflow->submit($business, $request->user());
        Directory::flushCounts();

        return redirect()->route('account.businesses.show', $business->uuid)->with('success',
            $business->status === BusinessStatus::Approved ? __('account.businesses.published_now') : __('account.businesses.submitted'));
    }

    public function archive(Request $request, Business $business, ListingWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('archive', $business);

        $workflow->archive($business, $request->user());
        Directory::flushCounts();

        return redirect()->route('account.businesses.show', $business->uuid)->with('success', __('account.businesses.archived'));
    }

    public function unarchive(Request $request, Business $business, ListingWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('unarchive', $business);

        $workflow->unarchive($business, $request->user());

        return redirect()->route('account.businesses.show', $business->uuid)->with('success', __('account.businesses.unarchived'));
    }

    public function destroy(Request $request, Business $business, BusinessManager $manager): RedirectResponse
    {
        $this->authorizeOwner($request, $business);
        $this->authorize('delete', $business);

        $manager->delete($business, $request->user());
        Directory::flushCounts();

        return redirect()->route('account.businesses.index')->with('success', __('account.businesses.deleted'));
    }

    /** The owner dashboard only ever shows the signed-in user's own listings (staff use the admin area). */
    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->isOwnedBy($request->user()), 404);
    }

    /** Percentage of recommended profile fields completed. */
    private function completeness(Business $b, ListingWorkflow $workflow): int
    {
        $checks = [
            filled($b->name_ar) && filled($b->name_en),
            filled($b->description_ar) && filled($b->description_en),
            filled($b->category_id),
            filled($b->municipality_id),
            filled($b->phone) || filled($b->whatsapp) || filled($b->email) || filled($b->website),
            filled($b->logo_path),
            filled($b->cover_path),
            filled($b->tagline_ar) || filled($b->tagline_en),
            filled($b->address_ar) || filled($b->address_en),
            $b->latitude !== null,
        ];

        return (int) round(100 * count(array_filter($checks)) / count($checks));
    }
}
