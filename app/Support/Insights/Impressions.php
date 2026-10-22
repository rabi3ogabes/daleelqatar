<?php

declare(strict_types=1);

namespace App\Support\Insights;

use Illuminate\Support\Facades\DB;

/**
 * "Search appearances": how often a listing was shown in search results,
 * category, location and guide pages. The page lists its businesses in a
 * signed attribute and a small script reports it once the page is seen, so
 * CDN-cached pages count too and crawlers do not.
 */
final class Impressions
{
    public const MAX_IDS = 60;

    /** @param  iterable<int>  $ids */
    public static function payload(iterable $ids): ?string
    {
        $ids = array_values(array_unique(array_map('intval', is_array($ids) ? $ids : iterator_to_array($ids))));
        $ids = array_slice(array_filter($ids, fn ($id) => $id > 0), 0, self::MAX_IDS);
        if ($ids === []) {
            return null;
        }
        $list = implode('.', $ids);

        return $list.'~'.self::sign($list);
    }

    /** @return list<int>|null */
    public static function verify(string $payload): ?array
    {
        if (! preg_match('/^([0-9]+(?:\.[0-9]+){0,'.(self::MAX_IDS - 1).'})~([a-f0-9]{20})$/', $payload, $m)) {
            return null;
        }
        if (! hash_equals(self::sign($m[1]), $m[2])) {
            return null;
        }

        return array_map('intval', explode('.', $m[1]));
    }

    /** @param  list<int>  $ids */
    public static function record(array $ids, string $column = 'impressions'): void
    {
        if ($ids === [] || ! in_array($column, ['impressions', 'qr_scans'], true)) {
            return;
        }
        $day = now()->toDateString();
        $rows = implode(',', array_fill(0, count($ids), '(?, ?, 1)'));
        $bindings = [];
        foreach ($ids as $id) {
            array_push($bindings, $id, $day);
        }

        // Ids that no longer exist are skipped by the join instead of failing the whole insert.
        DB::statement(
            "INSERT INTO business_daily_stats (business_id, day, {$column})
             SELECT v.business_id::bigint, v.day::date, v.n FROM (VALUES {$rows}) AS v(business_id, day, n)
             JOIN businesses b ON b.id = v.business_id::bigint
             ON CONFLICT (business_id, day) DO UPDATE SET {$column} = business_daily_stats.{$column} + 1",
            $bindings
        );
    }

    private static function sign(string $list): string
    {
        return substr(hash_hmac('sha256', 'impressions:'.$list, (string) config('app.key')), 0, 20);
    }
}
