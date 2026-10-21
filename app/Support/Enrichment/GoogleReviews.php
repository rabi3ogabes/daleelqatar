<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use Illuminate\Support\Facades\Cache;

/**
 * Google rating and the latest reviews for a linked listing, fetched live
 * from the Places API and kept only in a short-lived cache (never in the
 * database). Shown with Google's attribution, never as our own reviews.
 */
final class GoogleReviews
{
    private const FIELDS = ['rating', 'userRatingCount', 'reviews', 'googleMapsUri', 'googleMapsLinks'];

    public function __construct(private readonly GooglePlaces $places) {}

    /** @return array{rating: ?float, count: int, maps_url: string, reviews_url: string, write_url: ?string, reviews: list<array>}|null */
    public function for(string $placeId, string $locale): ?array
    {
        $hours = max(0, min(720, (int) setting('integrations.google_cache_hours', 24)));
        $fetch = function () use ($placeId, $locale) {
            try {
                return $this->normalize($this->places->details($placeId, $locale, self::FIELDS), $placeId);
            } catch (EnrichmentException $e) {
                report($e);

                return null;
            }
        };

        return $hours === 0 ? $fetch() : Cache::remember("google.reviews.$placeId.$locale", now()->addHours($hours), $fetch);
    }

    private function normalize(array $place, string $placeId): array
    {
        $maps = (string) (data_get($place, 'googleMapsUri') ?: GooglePlaces::mapsUrl($placeId));
        $reviews = [];
        foreach (array_slice((array) data_get($place, 'reviews', []), 0, 5) as $r) {
            $text = trim((string) data_get($r, 'text.text', data_get($r, 'originalText.text', '')));
            $reviews[] = [
                'author' => (string) data_get($r, 'authorAttribution.displayName', ''),
                'author_url' => safe_external_url((string) data_get($r, 'authorAttribution.uri')),
                'photo' => safe_external_url((string) data_get($r, 'authorAttribution.photoUri')),
                'rating' => (int) data_get($r, 'rating', 0),
                'text' => mb_substr($text, 0, 1500),
                'when' => (string) data_get($r, 'relativePublishTimeDescription', ''),
                'url' => safe_external_url((string) data_get($r, 'googleMapsUri')),
                'translated' => data_get($r, 'originalText.languageCode') !== null && data_get($r, 'originalText.languageCode') !== data_get($r, 'text.languageCode'),
            ];
        }

        return [
            'rating' => is_numeric(data_get($place, 'rating')) ? round((float) data_get($place, 'rating'), 1) : null,
            'count' => (int) data_get($place, 'userRatingCount', 0),
            'maps_url' => $maps,
            'reviews_url' => safe_external_url((string) data_get($place, 'googleMapsLinks.reviewsUri')) ?? $maps,
            'write_url' => safe_external_url((string) data_get($place, 'googleMapsLinks.writeAReviewUri')),
            'reviews' => $reviews,
        ];
    }
}
