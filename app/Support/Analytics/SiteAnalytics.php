<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reports over the site's own page views: who is on the site right now, and
 * for a period the visitors, pages, countries, devices and traffic sources.
 * Every number comes from recorded views — nothing is estimated.
 */
final class SiteAnalytics
{
    /** A visitor counts as "on the site now" when their page was open in the last 5 minutes. */
    public const ACTIVE_MINUTES = 5;

    public const RANGES = ['today', '7', '30', '90'];

    private function views(): Builder
    {
        return DB::table('page_views');
    }

    /** @return array{active: int, views: int, minutes: list<int>, pages: Collection, countries: Collection, devices: array<string,int>, sources: Collection} */
    public function realtime(): array
    {
        $since = now()->subMinutes(self::ACTIVE_MINUTES);
        $active = fn () => $this->views()->where('last_seen_at', '>=', $since);

        // Page views per minute over the last 30 minutes (oldest first).
        $start = CarbonImmutable::now()->startOfMinute()->subMinutes(29);
        $perMinute = $this->views()->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD HH24:MI') as m, count(*) as c")
            ->groupBy('m')->pluck('c', 'm');
        $minutes = [];
        for ($i = 0; $i < 30; $i++) {
            $minutes[] = (int) ($perMinute[$start->addMinutes($i)->format('Y-m-d H:i')] ?? 0);
        }

        return [
            'active' => (int) $active()->distinct()->count('visitor'),
            'views' => (int) $this->views()->where('created_at', '>=', now()->subMinutes(30))->count(),
            'minutes' => $minutes,
            'pages' => $active()->selectRaw('coalesce(page_key, path) as page, max(title) as title, max(path) as path, count(distinct visitor) as visitors')
                ->groupByRaw('coalesce(page_key, path)')->orderByDesc('visitors')->limit(8)->get(),
            'countries' => $active()->selectRaw('country, count(distinct visitor) as visitors')->groupBy('country')->orderByDesc('visitors')->limit(6)->get(),
            'devices' => $active()->selectRaw('device, count(distinct visitor) as visitors')->groupBy('device')->pluck('visitors', 'device')->map(fn ($n) => (int) $n)->all(),
            'sources' => $active()->selectRaw('channel, count(distinct visitor) as visitors')->groupBy('channel')->orderByDesc('visitors')->get(),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool} start, end, hourly buckets */
    public static function period(string $range): array
    {
        $now = CarbonImmutable::now();
        if ($range === 'today') {
            return [$now->startOfDay(), $now->addMinute(), true];
        }
        $days = max(1, (int) $range);

        return [$now->startOfDay()->subDays($days - 1), $now->addMinute(), false];
    }

    /** @return array<string, mixed> */
    public function overview(string $range): array
    {
        [$from, $to, $hourly] = self::period($range);
        $previousFrom = $from->subSeconds($to->getTimestamp() - $from->getTimestamp());
        $in = fn (CarbonImmutable $a, CarbonImmutable $b) => $this->views()->where('created_at', '>=', $a)->where('created_at', '<', $b);

        $current = $this->totals($in($from, $to));
        $previous = $this->totals($in($previousFrom, $from));
        $trend = fn (string $k) => $previous[$k] > 0 ? round(($current[$k] - $previous[$k]) / $previous[$k] * 100, 1) : null;

        // Times are stored in the application's time zone (Doha), like every other table here.
        $bucket = $hourly ? "to_char(created_at, 'HH24')" : "to_char(created_at, 'YYYY-MM-DD')";
        $rows = $in($from, $to)->selectRaw("{$bucket} as b, count(*) as views, count(distinct visitor) as visitors")->groupBy('b')->get()->keyBy('b');
        $labels = [];
        $views = [];
        $visitors = [];
        $steps = $hourly ? (int) CarbonImmutable::now()->format('G') + 1 : (int) $from->diffInDays(CarbonImmutable::now()->startOfDay()) + 1;
        for ($i = 0; $i < $steps; $i++) {
            $point = $hourly ? $from->addHours($i) : $from->addDays($i);
            $key = $hourly ? $point->format('H') : $point->toDateString();
            $labels[] = $hourly ? $point->format('H:00') : $point->locale(app()->getLocale())->isoFormat('D MMM');
            $views[] = (int) ($rows[$key]->views ?? 0);
            $visitors[] = (int) ($rows[$key]->visitors ?? 0);
        }

        $group = fn (string $column, int $limit = 8) => $in($from, $to)->selectRaw("{$column} as name, count(distinct visitor) as visitors, count(*) as views")
            ->groupBy($column)->orderByDesc('visitors')->limit($limit)->get();

        return [
            'from' => $from,
            'to' => $to,
            'hourly' => $hourly,
            'totals' => $current,
            'trends' => [
                'visitors' => $trend('visitors'),
                'new_visitors' => $trend('new_visitors'),
                'views' => $trend('views'),
                'engagement' => $trend('engagement'),
                'bounce' => $trend('bounce'),
            ],
            'series' => ['labels' => $labels, 'views' => $views, 'visitors' => $visitors],
            'pages' => $in($from, $to)->selectRaw('coalesce(page_key, path) as page, max(title) as title, min(path) as path, count(*) as views, count(distinct visitor) as visitors, round(avg(engaged_seconds)) as engaged')
                ->groupByRaw('coalesce(page_key, path)')->orderByDesc('views')->limit(15)->get(),
            'countries' => $group('country', 12),
            'cities' => $in($from, $to)->whereNotNull('city')->selectRaw('city as name, max(country) as country, count(distinct visitor) as visitors')->groupBy('city')->orderByDesc('visitors')->limit(8)->get(),
            'devices' => $group('device', 3),
            'browsers' => $group('browser', 6),
            'systems' => $group('os', 6),
            'channels' => $group('channel', 7),
            'referrers' => $in($from, $to)->whereNotNull('referrer')->selectRaw('referrer as name, max(channel) as channel, count(distinct visitor) as visitors')->groupBy('referrer')->orderByDesc('visitors')->limit(10)->get(),
            'languages' => $group('locale', 2),
        ];
    }

    /** @return array{visitors: int, new_visitors: int, views: int, sessions: int, per_visitor: float, engagement: int, bounce: float} */
    private function totals(Builder $query): array
    {
        $row = (clone $query)->selectRaw('count(*) as views, count(distinct visitor) as visitors, count(distinct session) as sessions,
            count(distinct case when is_new then visitor end) as new_visitors, coalesce(sum(engaged_seconds), 0) as engaged')->first();
        $sessions = (int) ($row->sessions ?? 0);
        // A bounce is a visit that saw only one page.
        $bounced = $sessions > 0 ? (int) DB::query()->fromSub((clone $query)->selectRaw('session')->groupBy('session')->havingRaw('count(*) = 1'), 's')->count() : 0;
        $visitors = (int) ($row->visitors ?? 0);

        return [
            'visitors' => $visitors,
            'new_visitors' => (int) ($row->new_visitors ?? 0),
            'views' => (int) ($row->views ?? 0),
            'sessions' => $sessions,
            'per_visitor' => $visitors > 0 ? round(((int) $row->views) / $visitors, 1) : 0.0,
            'engagement' => $sessions > 0 ? (int) round(((int) $row->engaged) / $sessions) : 0,
            'bounce' => $sessions > 0 ? round($bounced / $sessions * 100, 1) : 0.0,
        ];
    }
}
