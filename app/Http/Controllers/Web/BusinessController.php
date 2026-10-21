<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\BusinessEventType;
use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Services\AnalyticsRecorder;
use App\Services\ClaimService;
use App\Services\ReviewService;
use App\Support\Directory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function show(Business $business, AnalyticsRecorder $analytics, ReviewService $reviews, ClaimService $claims): View
    {
        // Drafts, pending, rejected and suspended listings are only visible to their owner and staff.
        abort_unless(Gate::allows('view', $business), 404);

        $business->load([
            'category.parent', 'categories', 'municipality', 'area', 'images', 'services', 'openingHours', 'socialLinks',
            'activeOffers' => fn ($q) => $q->orderBy('ends_at'),
        ]);

        $user = auth()->user();
        $approvedReviews = $business->approvedReviews()->with('user:id,name')->latest()->paginate(6, ['*'], 'reviews_page');
        $ownReview = $user ? Review::query()->where('business_id', $business->id)->where('user_id', $user->id)->first() : null;

        $similar = Business::query()->published()->whereKeyNot($business->id)
            ->where('category_id', $business->category_id)
            ->with(Directory::cardRelations())
            ->orderByRaw('CASE WHEN municipality_id = ? THEN 0 ELSE 1 END', [$business->municipality_id])
            ->orderByDesc('is_verified')->latest('published_at')->limit(4)->get();

        if ($business->isPublished()) {
            $analytics->recordView($business);
        }

        $name = $business->t('name');
        seo()->title($business->t('meta_title') ?: trim($name.' — '.$business->category?->t('name').($business->municipality ? ' '.__('search.in_location', ['location' => $business->municipality->t('name')]) : '')))
            ->description($business->t('meta_description') ?: ($business->t('tagline') ?: $business->t('description')))
            ->image($business->coverUrl() ?? $business->logoUrl())
            ->type('business.business')
            ->noindex(! $business->isPublished() || $business->is_demo)
            ->jsonLd($this->schema($business));

        return view('web.business.show', [
            'business' => $business,
            'reviews' => $approvedReviews,
            'ownReview' => $ownReview,
            'similar' => $similar,
            'isFavorite' => in_array($business->id, Directory::favoriteIds(), true),
            'reviewsEnabled' => $reviews->enabled(),
            'canClaim' => $claims->canBeClaimed($business) && ! $business->isOwnedBy($user),
            'reportReasons' => ReportReason::cases(),
            'reviewReportReasons' => ReportReason::forReviews(),
            'openNow' => $business->isOpenNow(),
            'today' => now(Business::TIMEZONE)->dayOfWeek,
            'distribution' => $business->reviews_count > 0
                ? $business->approvedReviews()->selectRaw('rating, COUNT(*) AS total')->groupBy('rating')->pluck('total', 'rating')->all()
                : [],
        ]);
    }

    public function featured(): View
    {
        $results = Business::query()->published()->featured()->with(Directory::cardRelations())
            ->orderByDesc('is_verified')->latest('published_at')->paginate(12);

        seo()->title(__('business.featured_page_title'))->description(__('business.featured_page_text'))->noindex($results->total() === 0);

        return view('web.business.collection', [
            'results' => $results,
            'title' => __('business.featured_page_title'),
            'text' => __('business.featured_page_text'),
            'icon' => 'sparkles',
            'favoriteIds' => Directory::favoriteIds(),
        ]);
    }

    public function latest(): View
    {
        $results = Business::query()->published()->with(Directory::cardRelations())->latest('published_at')->paginate(12);

        seo()->title(__('business.latest_page_title'))->description(__('business.latest_page_text'))->noindex($results->total() === 0);

        return view('web.business.collection', [
            'results' => $results,
            'title' => __('business.latest_page_title'),
            'text' => __('business.latest_page_text'),
            'icon' => 'clock',
            'favoriteIds' => Directory::favoriteIds(),
        ]);
    }

    /** Records contact interactions (call, WhatsApp, website, directions, share) for owner insights. */
    public function track(Request $request, Business $business, AnalyticsRecorder $analytics): Response
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, BusinessEventType::trackable()))],
        ]);

        if ($business->isPublished()) {
            $analytics->recordInteraction($business, BusinessEventType::from($data['type']));
        }

        return response()->noContent();
    }

    private function schema(Business $business): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => route('business.show', $business).'#business',
            'name' => $business->t('name'),
            'alternateName' => $business->t('name', app()->getLocale() === 'ar' ? 'en' : 'ar'),
            'description' => $business->t('description'),
            'url' => route('business.show', $business),
            'image' => array_values(array_filter([$business->coverUrl(), $business->logoUrl(), ...$business->images->take(5)->map->url()->all()])),
            'logo' => $business->logoUrl(),
            'telephone' => $business->phone,
            'email' => $business->email,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $business->t('address'),
                'addressLocality' => $business->area?->t('name') ?? $business->municipality?->t('name'),
                'addressRegion' => $business->municipality?->t('name'),
                'addressCountry' => 'QA',
            ]),
            'geo' => $business->latitude !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $business->latitude, 'longitude' => $business->longitude] : null,
            'openingHoursSpecification' => $business->openingHours->where('is_closed', false)->map(fn ($h) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days[$h->day_of_week],
                'opens' => substr((string) $h->opens_at, 0, 5),
                'closes' => str_starts_with((string) $h->closes_at, '24') ? '23:59' : substr((string) $h->closes_at, 0, 5),
            ])->values()->all() ?: null,
            'sameAs' => $business->socialLinks->pluck('url')->all() ?: null,
            // Only real, moderated ratings are exposed to search engines.
            'aggregateRating' => $business->reviews_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $business->rating_avg, 1),
                'reviewCount' => $business->reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
