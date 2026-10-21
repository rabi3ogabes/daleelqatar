<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessStatus;
use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Offer;
use App\Services\MediaService;
use App\Services\OfferService;
use App\Support\AdminNavigation;
use App\Support\Sitemap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $offers = Offer::query()->with(['business:id,name_ar,name_en,slug', 'creator:id,name'])
            ->when(OfferStatus::tryFrom((string) $status), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('timing') === 'live', fn ($q) => $q->where('starts_at', '<=', now())->where('ends_at', '>', now()))
            ->when($request->query('timing') === 'expired', fn ($q) => $q->where('ends_at', '<=', now()))
            ->latest()->paginate(15)->withQueryString();
        $counts = Offer::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.offers.index', ['offers' => $offers, 'status' => $status, 'counts' => $counts]);
    }

    public function moderate(Request $request, Offer $offer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([OfferStatus::Approved->value, OfferStatus::Rejected->value])],
            'reason' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('status') === OfferStatus::Rejected->value)],
        ]);
        $this->offers->moderate($offer, $request->user(), OfferStatus::from($data['status']), $data['reason'] ?? null);
        AdminNavigation::flush();
        Sitemap::flush();

        return back()->with('success', __('admin.offers.moderated'));
    }

    public function create(): View
    {
        return view('admin.offers.form', ['offer' => new Offer(['is_active' => true, 'starts_at' => now(), 'ends_at' => now()->addMonth()]), 'businesses' => $this->businessOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $business = Business::query()->findOrFail($data['business_id']);
        $offer = $this->offers->create($business, $request->user(), collect($data)->except(['business_id', 'image'])->all(), $request->file('image'));
        Sitemap::flush();

        return redirect()->route('admin.offers.index', ['status' => $offer->status->value])->with('success', __('admin.offers.created'));
    }

    public function edit(Offer $offer): View
    {
        return view('admin.offers.form', ['offer' => $offer->load('business:id,name_ar,name_en'), 'businesses' => $this->businessOptions()]);
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        $data = $this->validated($request, false);
        $this->offers->update($offer, $request->user(), collect($data)->except(['image'])->all(), $request->file('image'));
        Sitemap::flush();

        return redirect()->route('admin.offers.index', ['status' => $offer->status->value])->with('success', __('common.updated'));
    }

    public function destroy(Request $request, Offer $offer): RedirectResponse
    {
        $this->offers->delete($offer, $request->user());
        AdminNavigation::flush();

        return back()->with('success', __('common.deleted'));
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'business_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('businesses', 'id')->where('status', BusinessStatus::Approved->value)->whereNull('deleted_at')],
            'title_ar' => ['required', 'string', 'max:160'],
            'title_en' => ['required', 'string', 'max:160'],
            'description_ar' => ['nullable', 'string', 'max:3000'],
            'description_en' => ['nullable', 'string', 'max:3000'],
            'discount_label_ar' => ['nullable', 'string', 'max:40'],
            'discount_label_en' => ['nullable', 'string', 'max:40'],
            'terms_ar' => ['nullable', 'string', 'max:3000'],
            'terms_en' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'image' => ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function businessOptions(): array
    {
        return Business::query()->where('status', BusinessStatus::Approved)->orderBy(Business::localizedColumn('name'))
            ->get(['id', 'name_ar', 'name_en'])->mapWithKeys(fn ($b) => [$b->id => $b->t('name')])->all();
    }
}
