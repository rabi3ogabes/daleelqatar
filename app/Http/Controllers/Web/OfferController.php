<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\AnalyticsRecorder;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(): View
    {
        $offers = Offer::query()->live()->with('business:id,name_ar,name_en,slug')
            ->orderByDesc('is_featured')->orderBy('ends_at')->paginate(12);

        seo()->title(__('offers.title'))->description(__('offers.meta_description'))->noindex($offers->total() === 0);

        return view('web.offers.index', ['offers' => $offers]);
    }

    public function show(Offer $offer, AnalyticsRecorder $analytics): View
    {
        $offer->load(['business.category', 'business.municipality', 'business.area']);
        $live = $offer->isLive() && $offer->business?->isPublished();
        $canPreview = auth()->user()?->can('update', $offer) ?? false;
        abort_unless($live || $canPreview || ($offer->isExpired() && $offer->business?->isPublished()), 404);

        if ($live) {
            $analytics->recordOfferView($offer);
        }

        seo()->title($offer->t('title').' — '.$offer->business->t('name'))
            ->description($offer->t('description'))
            ->image($offer->imageUrl())
            ->noindex(! $live);

        return view('web.offers.show', ['offer' => $offer, 'live' => $live]);
    }
}
