<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a listing has enough of its own content to be worth a search
 * engine's (and an advertiser's) attention. A listing with only a name and a
 * number is useful to visitors but "thin" to Google: it stays on the site,
 * but is kept out of search results and the sitemap, and shows no ads, until
 * it has a description or enough details.
 */
final class ListingQuality
{
    public const MIN_DESCRIPTION = 80;

    public const MIN_FACTS = 4;

    public static function enabled(): bool
    {
        return (bool) setting('seo.noindex_thin_listings', true);
    }

    public static function isRich(Business $business): bool
    {
        if (mb_strlen(trim((string) $business->description_en)) >= self::MIN_DESCRIPTION || mb_strlen(trim((string) $business->description_ar)) >= self::MIN_DESCRIPTION) {
            return true;
        }

        return self::facts($business) >= self::MIN_FACTS;
    }

    /** Distinct things a visitor learns from the page besides the name and category. */
    public static function facts(Business $business): int
    {
        return (int) filled($business->phone ?: $business->whatsapp ?: $business->email)
            + (int) filled($business->website)
            + (int) filled($business->address_en ?: $business->address_ar)
            + (int) ($business->latitude !== null)
            + (int) filled($business->logo_path)
            + (int) ($business->reviews_count > 0)
            + (int) filled($business->google_place_id)
            + (int) filled($business->opening_hours_text);
    }

    /** The same rule in SQL, for the sitemap and the readiness report. */
    public static function scopeRich(Builder $query): Builder
    {
        $min = self::MIN_DESCRIPTION;
        $facts = "(CASE WHEN COALESCE(NULLIF(businesses.phone, ''), NULLIF(businesses.whatsapp, ''), NULLIF(businesses.email, '')) IS NOT NULL THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.website, '') <> '' THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(NULLIF(businesses.address_en, ''), NULLIF(businesses.address_ar, '')) IS NOT NULL THEN 1 ELSE 0 END)
            + (CASE WHEN businesses.latitude IS NOT NULL THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.logo_path, '') <> '' THEN 1 ELSE 0 END)
            + (CASE WHEN businesses.reviews_count > 0 THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.google_place_id, '') <> '' THEN 1 ELSE 0 END)
            + (CASE WHEN COALESCE(businesses.opening_hours_text, '') <> '' THEN 1 ELSE 0 END)";

        return $query->where(fn ($q) => $q
            ->whereRaw("char_length(trim(COALESCE(businesses.description_en, ''))) >= {$min}")
            ->orWhereRaw("char_length(trim(COALESCE(businesses.description_ar, ''))) >= {$min}")
            ->orWhereRaw("({$facts}) >= ?", [self::MIN_FACTS]));
    }
}
