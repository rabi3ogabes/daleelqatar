<?php

declare(strict_types=1);

namespace App\Support\Enrichment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** How complete the directory is — shown on the admin dashboard and the enrichment page. */
final class DataQuality
{
    /**
     * Listings still due for a check: never checked, or their last check was refused by Google
     * (quota, key or project settings) — those are retried once Google answers.
     */
    public const DUE_SQL = <<<'SQL'
        (businesses.enriched_at is null or (businesses.google_place_id is null and exists (
            select 1 from business_suggestions s
            where s.id = (select max(s2.id) from business_suggestions s2 where s2.business_id = businesses.id)
              and (s.notes::jsonb ->> 'google') like 'google_error:%')))
        SQL;

    /** @return array<string, int> */
    public static function stats(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('enrichment.quality');
        }

        return Cache::remember('enrichment.quality', now()->addMinutes(2), function () {
            $row = DB::table('businesses')->whereNull('deleted_at')->where('is_demo', false)->selectRaw(<<<'SQL'
                count(*) as total,
                count(*) filter (where coalesce(name_ar, '') !~ '[؀-ۿ]') as missing_arabic,
                count(*) filter (where coalesce(description_en, '') = '' or coalesce(description_ar, '') = '') as missing_description,
                count(*) filter (where coalesce(website, '') = '') as missing_website,
                count(*) filter (where website_status = 'none') as no_website_found,
                count(*) filter (where latitude is null) as missing_location,
                SQL.' count(*) filter (where '.self::DUE_SQL.') as not_enriched,'.<<<'SQL'
                count(*) filter (where google_place_id is not null) as on_google
                SQL)->first();

            return array_map('intval', (array) $row) + ['pending' => DB::table('business_suggestions')->where('status', 'pending')->count()];
        });
    }
}
