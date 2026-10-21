<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessEventType;
use App\Models\Business;
use App\Models\BusinessEvent;
use App\Models\Offer;
use App\Models\SearchEvent;
use App\Support\Search\ArabicNormalizer;
use App\Support\Search\SearchCriteria;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records first-party analytics events. Personal data is minimised: IP
 * addresses are stored only as salted hashes and bots are ignored.
 * Writes are deferred until after the response has been sent.
 */
class AnalyticsRecorder
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse/i';

    public function __construct(private readonly SettingsService $settings) {}

    public function recordView(Business $business, string $source = 'web'): void
    {
        if (! $this->settings->bool('analytics.track_views') || $this->isBot()) {
            return;
        }
        $ip = ip_hash();
        // Count one view per visitor per listing every 30 minutes.
        if (! Cache::add('view:'.$business->id.':'.$ip, 1, now()->addMinutes(30))) {
            return;
        }
        $userId = auth()->id();

        dispatch(function () use ($business, $source, $ip, $userId) {
            DB::transaction(function () use ($business, $source, $ip, $userId) {
                BusinessEvent::query()->create([
                    'business_id' => $business->id, 'type' => BusinessEventType::View, 'source' => $source,
                    'user_id' => $userId, 'ip_hash' => $ip,
                ]);
                Business::query()->whereKey($business->id)->increment('views_count');
            });
        })->afterResponse();
    }

    public function recordInteraction(Business $business, BusinessEventType $type, string $source = 'web'): void
    {
        if ($this->isBot()) {
            return;
        }
        $ip = ip_hash();
        if (! Cache::add('evt:'.$type->value.':'.$business->id.':'.$ip, 1, now()->addMinutes(10))) {
            return;
        }
        BusinessEvent::query()->create([
            'business_id' => $business->id, 'type' => $type, 'source' => $source,
            'user_id' => auth()->id(), 'ip_hash' => $ip,
        ]);
    }

    public function recordOfferView(Offer $offer, string $source = 'web'): void
    {
        if (! $this->settings->bool('analytics.track_views') || $this->isBot()) {
            return;
        }
        $ip = ip_hash();
        if (! Cache::add('offer:'.$offer->id.':'.$ip, 1, now()->addMinutes(30))) {
            return;
        }
        $userId = auth()->id();
        dispatch(function () use ($offer, $source, $ip, $userId) {
            BusinessEvent::query()->create([
                'business_id' => $offer->business_id, 'offer_id' => $offer->id, 'type' => BusinessEventType::OfferView,
                'source' => $source, 'user_id' => $userId, 'ip_hash' => $ip,
            ]);
            Offer::query()->whereKey($offer->id)->increment('views_count');
        })->afterResponse();
    }

    public function recordSearch(SearchCriteria $criteria, int $results, string $source = 'web'): void
    {
        if (! $this->settings->bool('analytics.track_searches') || $this->isBot() || ! $criteria->hasFilters() || $criteria->page > 1) {
            return;
        }
        $data = [
            'query' => $criteria->query ? Str::limit($criteria->query, 190, '') : null,
            'normalized_query' => $criteria->query ? Str::limit(ArabicNormalizer::normalize($criteria->query), 190, '') : null,
            'category_id' => $criteria->category?->id,
            'municipality_id' => $criteria->municipality?->id,
            'results_count' => $results,
            'locale' => app()->getLocale(),
            'source' => $source,
            'user_id' => auth()->id(),
            'ip_hash' => ip_hash(),
        ];
        dispatch(fn () => SearchEvent::query()->create($data))->afterResponse();
    }

    private function isBot(): bool
    {
        $ua = (string) request()->userAgent();

        return $ua === '' || preg_match(self::BOT_PATTERN, $ua) === 1;
    }
}
