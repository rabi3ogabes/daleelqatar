<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use App\Models\Business;
use App\Support\Import\Normalizer;

/**
 * Scores how likely a Google Maps place is the same business as a listing
 * (0–100) and explains why, so the admin can trust or correct the match.
 */
final class PlaceMatcher
{
    public const MIN_SCORE = 45;

    /**
     * @param  list<array<string,mixed>>  $places
     * @return list<array{place: array, score: int, reasons: list<string>}> best first
     */
    public function rank(Business $business, array $places): array
    {
        $ranked = array_map(fn (array $place) => ['place' => $place] + $this->score($business, $place), $places);
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }

    /** @return array{score: int, reasons: list<string>} */
    public function score(Business $business, array $place): array
    {
        $reasons = [];
        $name = (string) data_get($place, 'displayName.text');

        $similarity = max(
            Normalizer::nameSimilarity($business->name_en, $name),
            Normalizer::hasArabic((string) $business->name_ar) ? Normalizer::nameSimilarity($business->name_ar, $name) : 0.0,
        );
        $score = (int) round($similarity * 70);
        $reasons[] = $similarity >= 0.9 ? 'name_exact' : ($similarity >= 0.6 ? 'name_similar' : 'name_different');

        $placePhone = Normalizer::phone((string) (data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber')));
        $ours = array_filter([Normalizer::phone($business->phone), Normalizer::phone($business->whatsapp), ...array_map(fn ($p) => Normalizer::phone((string) $p), (array) $business->extra_phones)]);
        if ($placePhone !== null && $ours !== []) {
            if (in_array($placePhone, $ours, true)) {
                $score += 30;
                $reasons[] = 'phone_match';
            } else {
                $score -= 10;
                $reasons[] = 'phone_different';
            }
        }

        $placeDomain = Normalizer::domain((string) data_get($place, 'websiteUri'));
        $ourDomain = Normalizer::domain($business->website) ?? ($business->email ? Normalizer::emailDomain($business->email) : null);
        if ($placeDomain !== null && $ourDomain !== null && $placeDomain === $ourDomain) {
            $score += 25;
            $reasons[] = 'website_match';
        }

        $lat = data_get($place, 'location.latitude');
        $lng = data_get($place, 'location.longitude');
        if ($business->latitude !== null && $business->longitude !== null && is_numeric($lat) && is_numeric($lng)) {
            $km = self::distanceKm($business->latitude, $business->longitude, (float) $lat, (float) $lng);
            if ($km <= 0.3) {
                $score += 15;
                $reasons[] = 'location_match';
            } elseif ($km > 5) {
                $score -= 25;
                $reasons[] = 'location_far';
            }
        }

        $address = mb_strtolower((string) data_get($place, 'formattedAddress'));
        $area = mb_strtolower((string) ($business->area?->name_en ?? $business->municipality?->name_en ?? ''));
        if ($area !== '' && str_contains($address, $area)) {
            $score += 5;
            $reasons[] = 'area_match';
        }

        if (data_get($place, 'businessStatus') === 'CLOSED_PERMANENTLY') {
            $reasons[] = 'closed_permanently';
        }

        return ['score' => max(0, min(100, $score)), 'reasons' => $reasons];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371.0 * asin(min(1.0, sqrt($a)));
    }
}
