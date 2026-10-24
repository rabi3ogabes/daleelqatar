<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Support\Deadline;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Places API (New). Uses the official API — Google Maps pages are
 * never scraped. Field masks keep each call to the fields we need.
 */
final class GooglePlaces
{
    private const BASE = 'https://places.googleapis.com/v1';

    /** Qatar bounding box: searches never leave the country. */
    private const QATAR = ['low' => ['latitude' => 24.45, 'longitude' => 50.70], 'high' => ['latitude' => 26.20, 'longitude' => 51.70]];

    /** Place fields used to match and enrich a listing. */
    public const PLACE_FIELDS = [
        'id', 'displayName', 'formattedAddress', 'location', 'googleMapsUri',
        'nationalPhoneNumber', 'internationalPhoneNumber', 'websiteUri', 'businessStatus',
        'primaryTypeDisplayName', 'regularOpeningHours', 'rating', 'userRatingCount',
    ];

    public static function key(): ?string
    {
        $key = trim((string) config('services.google_places.key'));

        return $key !== '' ? $key : null;
    }

    /** Matching listings to Google Maps (admin enrichment). */
    public static function enabled(): bool
    {
        return self::key() !== null && (bool) setting('integrations.google_places', true);
    }

    /** Showing the Google rating and reviews on public profiles. */
    public static function reviewsEnabled(): bool
    {
        return self::enabled() && (bool) setting('integrations.google_reviews', true);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $query, string $language = 'en', int $max = 5): array
    {
        $data = $this->call('post', '/places:searchText', array_map(fn ($f) => 'places.'.$f, self::PLACE_FIELDS), [
            'textQuery' => $query,
            'languageCode' => $language,
            'regionCode' => 'QA',
            'maxResultCount' => $max,
            'locationRestriction' => ['rectangle' => self::QATAR],
        ]);

        return array_values((array) ($data['places'] ?? []));
    }

    /** Place fields shown in the Google Maps finder's result list (no hours: they are read when a place is added). */
    public const AREA_FIELDS = [
        'id', 'displayName', 'formattedAddress', 'location', 'googleMapsUri', 'types',
        'nationalPhoneNumber', 'internationalPhoneNumber', 'websiteUri', 'businessStatus',
        'primaryType', 'primaryTypeDisplayName', 'rating', 'userRatingCount',
    ];

    /**
     * One page (up to 20) of the places matching a query inside a rectangle (Qatar when none is given).
     *
     * @param  array{low: array{latitude: float, longitude: float}, high: array{latitude: float, longitude: float}}|null  $rectangle
     * @return array{places: list<array<string,mixed>>, next: ?string}
     */
    public function searchArea(string $query, ?array $rectangle = null, ?string $pageToken = null, string $language = 'en'): array
    {
        $payload = [
            'textQuery' => $query,
            'languageCode' => $language,
            'regionCode' => 'QA',
            'pageSize' => 20,
            'locationRestriction' => ['rectangle' => $rectangle ?? self::QATAR],
        ];
        if ($pageToken !== null) {
            $payload['pageToken'] = $pageToken;
        }
        $data = $this->call('post', '/places:searchText', [...array_map(fn ($f) => 'places.'.$f, self::AREA_FIELDS), 'nextPageToken'], $payload);
        $next = $data['nextPageToken'] ?? null;

        return ['places' => array_values((array) ($data['places'] ?? [])), 'next' => is_string($next) && $next !== '' ? $next : null];
    }

    /**
     * The rectangle around a point (radius in km), kept inside Qatar.
     *
     * @return array{low: array{latitude: float, longitude: float}, high: array{latitude: float, longitude: float}}
     */
    public static function rectangleAround(float $lat, float $lng, float $radiusKm): array
    {
        $dLat = $radiusKm / 111.32;
        $dLng = $radiusKm / (111.32 * max(0.2, cos(deg2rad($lat))));

        return [
            'low' => ['latitude' => max(self::QATAR['low']['latitude'], $lat - $dLat), 'longitude' => max(self::QATAR['low']['longitude'], $lng - $dLng)],
            'high' => ['latitude' => min(self::QATAR['high']['latitude'], $lat + $dLat), 'longitude' => min(self::QATAR['high']['longitude'], $lng + $dLng)],
        ];
    }

    /** @param list<string> $fields Place fields without the "places." prefix */
    public function details(string $placeId, string $language, array $fields): array
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{10,255}$/', $placeId)) {
            throw new EnrichmentException('invalid_place_id');
        }

        return $this->call('get', '/places/'.$placeId, $fields, ['languageCode' => $language, 'regionCode' => 'QA']);
    }

    /** Public Maps link for a place (allowed to keep: it is derived from the Place ID). */
    public static function mapsUrl(string $placeId): string
    {
        return 'https://www.google.com/maps/place/?q=place_id:'.rawurlencode($placeId);
    }

    private function call(string $method, string $path, array $fields, array $payload): array
    {
        $key = self::key() ?? throw new EnrichmentException('google_not_configured');
        if (! Deadline::has(4)) {
            throw new EnrichmentException('google_out_of_time');
        }

        try {
            $request = Http::withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => implode(',', $fields)])
                ->acceptJson()->timeout((int) ceil(Deadline::timeout(15)))->connectTimeout(5)->retry(Deadline::has(35) ? 2 : 1, 400, throw: false);
            $response = $method === 'post' ? $request->post(self::BASE.$path, $payload) : $request->get(self::BASE.$path, $payload);
        } catch (ConnectionException) {
            throw new EnrichmentException('google_unreachable');
        }

        if ($response->status() === 404) {
            throw new EnrichmentException('google_place_not_found');
        }
        if ($response->failed()) {
            $status = (string) data_get($response->json(), 'error.status', 'HTTP_'.$response->status());
            throw new EnrichmentException('google_error:'.$status);
        }

        return (array) $response->json();
    }
}
