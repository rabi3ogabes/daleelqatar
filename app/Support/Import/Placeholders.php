<?php

declare(strict_types=1);

namespace App\Support\Import;

use Illuminate\Support\Str;

/**
 * Values directories show when they do not really know: the same pin in the
 * middle of Doha for every company, or "Doha, Qatar" as the address. Imported
 * as facts they would put thousands of listings on one spot of the map, so
 * they are recognised and dropped (the city is kept).
 */
final class Placeholders
{
    /** Default pins seen on Qatar directories and geocoders (lat, lng). */
    private const COORDINATES = [
        [25.2854, 51.5310],   // Doha centre (QBCD and many map widgets)
        [25.2867, 51.5333],   // Doha (Google)
        [25.276987, 51.520008], // Doha (geocoders)
        [25.3548, 51.1839],   // Qatar centroid
        [25.354826, 51.183884],
        [0.0, 0.0],
    ];

    private const TOLERANCE = 0.0006;

    /** Place names that alone are not an address. */
    private const PLACES = ['doha', 'qatar', 'state of qatar', 'ad dawhah', 'ad-dawhah', 'al dawhah', 'dawhah', 'ad dawah', 'baladiyat ad dawhah', 'الدوحة', 'قطر', 'دولة قطر'];

    public static function isCoordinate(float|string|null $lat, float|string|null $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }
        foreach (self::COORDINATES as [$pLat, $pLng]) {
            if (abs((float) $lat - $pLat) <= self::TOLERANCE && abs((float) $lng - $pLng) <= self::TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    /** "Doha, Ad Dawḩah, Qatar" → true; "Building 12, Street 340, Al Sadd, Doha" → false. */
    public static function isGenericAddress(?string $address): bool
    {
        $tokens = self::tokens($address);

        return $tokens !== [] && array_diff($tokens, self::PLACES) === [];
    }

    /** The city a generic address names ("Doha"), if any. */
    public static function cityOf(?string $address): ?string
    {
        $tokens = self::tokens($address);

        return array_intersect($tokens, ['doha', 'ad dawhah', 'ad-dawhah', 'al dawhah', 'dawhah', 'ad dawah', 'baladiyat ad dawhah']) !== [] || in_array('الدوحة', $tokens, true) ? 'Doha' : null;
    }

    /** @return list<string> */
    private static function tokens(?string $address): array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return [];
        }
        $parts = preg_split('/\s*[,،\-–|\/]\s*/u', $address) ?: [];

        return array_values(array_filter(array_map(function ($part) {
            $part = trim($part);

            if (preg_match('/\p{Arabic}/u', $part)) {
                return $part;
            }
            // Strip accents by decomposition ("Dawḩah" → "Dawhah"); Str::ascii alone drops such letters.
            if (class_exists(\Normalizer::class)) {
                $part = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($part, \Normalizer::FORM_D));
            }

            return trim(Str::lower(Str::ascii($part)), " .'");
        }, $parts)));
    }
}
