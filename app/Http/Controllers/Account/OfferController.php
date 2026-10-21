<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\BusinessStatus;
use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\OfferRequest;
use App\Models\Business;
use App\Models\Offer;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $offers = Offer::query()->whereIn('business_id', $user->businesses()->select('id'))
            ->with('business:id,uuid,name_ar,name_en,slug,status,published_at')
            ->latest()->paginate(12);

        return view('account.offers.index', [
            'offers' => $offers,
            'eligible' => $this->eligibleBusinesses($request)->isNotEmpty(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $businesses = $this->eligibleBusinesses($request);
        if ($businesses->isEmpty()) {
            return redirect()->route('account.offers.index')->with('warning', __('account.offers.no_eligible'));
        }

        return view('account.offers.form', [
            'offer' => null,
            'businesses' => $businesses,
            'selected' => $request->query('business'),
        ]);
    }

    public function store(OfferRequest $request, OfferService $offers): RedirectResponse
    {
        $business = Business::query()->where('uuid', $request->input('business_id'))->firstOrFail();
        abort_unless($business->isOwnedBy($request->user()), 404);
        $this->authorize('manageOffers', $business);

        $offer = $offers->create($business, $request->user(), $request->offerData(), $request->file('image'));

        return redirect()->route('account.offers.index')->with('success',
            $offer->status === OfferStatus::Pending ? __('account.offers.created_pending') : __('account.offers.created'));
    }

    public function edit(Request $request, Offer $offer): View
    {
        $this->authorizeOwner($request, $offer);

        return view('account.offers.form', [
            'offer' => $offer->load('business:id,uuid,name_ar,name_en'),
            'businesses' => collect([$offer->business]),
            'selected' => $offer->business->uuid,
        ]);
    }

    public function update(OfferRequest $request, Offer $offer, OfferService $offers): RedirectResponse
    {
        $this->authorizeOwner($request, $offer);
        $offer = $offers->update($offer, $request->user(), $request->offerData(), $request->file('image'));

        return redirect()->route('account.offers.index')->with('success',
            $offer->status === OfferStatus::Pending ? __('account.offers.created_pending') : __('account.offers.updated'));
    }

    public function destroy(Request $request, Offer $offer, OfferService $offers): RedirectResponse
    {
        $this->authorizeOwner($request, $offer);
        $offers->delete($offer, $request->user());

        return redirect()->route('account.offers.index')->with('success', __('account.offers.deleted'));
    }

    private function authorizeOwner(Request $request, Offer $offer): void
    {
        abort_unless($offer->business?->isOwnedBy($request->user()), 404);
        $this->authorize('update', $offer);
    }

    private function eligibleBusinesses(Request $request)
    {
        return $request->user()->businesses()->where('status', BusinessStatus::Approved)
            ->orderBy(Business::localizedColumn('name'))->get(['id', 'uuid', 'name_ar', 'name_en']);
    }
}
