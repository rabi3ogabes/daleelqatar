<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\ModerationStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Offer;
use App\Models\User;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the administration dashboard comes from these queries.
 * Nothing is hard-coded; trends compare the last 30 days with the 30 days before.
 */
class DashboardMetrics
{
    /** @return array<string, int> */
    public function totals(): array
    {
        $businesses = DB::table('businesses')->whereNull('deleted_at');
        $byStatus = (clone $businesses)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);

        return [
            'businesses' => (int) $byStatus->sum(),
            'approved' => $byStatus[BusinessStatus::Approved->value] ?? 0,
            'pending' => $byStatus[BusinessStatus::Pending->value] ?? 0,
            'rejected_suspended' => ($byStatus[BusinessStatus::Rejected->value] ?? 0) + ($byStatus[BusinessStatus::Suspended->value] ?? 0),
            'drafts' => $byStatus[BusinessStatus::Draft->value] ?? 0,
            'users' => DB::table('users')->whereNull('deleted_at')->count(),
            'owners' => User::role(Permissions::BUSINESS_OWNER)->count(),
            'categories' => DB::table('categories')->count(),
            'active_offers' => Offer::query()->live()->count(),
            'pending_reviews' => DB::table('reviews')->whereNull('deleted_at')->where('status', ModerationStatus::Pending->value)->count(),
        ];
    }

    /** Percentage change (last 30 days vs previous 30 days). Null when there is no baseline. */
    public function trend(Builder $query, string $column = 'created_at', int $days = 30): ?float
    {
        $now = now();
        $current = (clone $query)->where($column, '>', $now->copy()->subDays($days))->count();
        $previous = (clone $query)->whereBetween($column, [$now->copy()->subDays($days * 2), $now->copy()->subDays($days)])->count();

        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    public function trends(): array
    {
        return [
            'businesses' => $this->trend(DB::table('businesses')->whereNull('deleted_at')),
            'approved' => $this->trend(DB::table('business_status_changes')->where('to_status', BusinessStatus::Approved->value)),
            'users' => $this->trend(DB::table('users')->whereNull('deleted_at')),
            'views' => $this->trend(DB::table('business_events')->where('type', 'view')),
        ];
    }

    /** @return list<CarbonImmutable> First day of each of the last N months (oldest first). */
    public function months(int $n = 12): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($n - 1);

        return array_map(fn ($i) => $start->addMonths($i), range(0, $n - 1));
    }

    public function monthLabels(int $n = 12): array
    {
        return array_map(fn (CarbonImmutable $m) => $m->locale(app()->getLocale())->isoFormat('MMM'), $this->months($n));
    }

    /** Monthly counts aligned to months(): [count, …]. */
    private function monthly(Builder $query, string $column = 'created_at', int $n = 12): array
    {
        $months = $this->months($n);
        $rows = (clone $query)->where($column, '>=', $months[0])
            ->selectRaw("to_char(date_trunc('month', {$column}), 'YYYY-MM') as m, count(*) as c")
            ->groupBy('m')->pluck('c', 'm');

        return array_map(fn (CarbonImmutable $m) => (int) ($rows[$m->format('Y-m')] ?? 0), $months);
    }

    /** Cumulative number of listings at the end of each month (business growth). */
    public function businessGrowth(int $n = 12): array
    {
        $months = $this->months($n);
        $base = DB::table('businesses')->whereNull('deleted_at')->where('created_at', '<', $months[0])->count();
        $running = $base;

        return array_map(function ($c) use (&$running) {
            $running += $c;

            return $running;
        }, $this->monthly(DB::table('businesses')->whereNull('deleted_at'), 'created_at', $n));
    }

    public function approvalsByMonth(int $n = 12): array
    {
        $q = DB::table('business_status_changes');

        return [
            'approved' => $this->monthly((clone $q)->where('to_status', BusinessStatus::Approved->value), 'created_at', $n),
            'rejected' => $this->monthly((clone $q)->where('to_status', BusinessStatus::Rejected->value), 'created_at', $n),
        ];
    }

    /** Published listings per root category, for the donut. */
    public function categoryDistribution(): array
    {
        $counts = Directory::categoryCounts();

        return app(NavigationData::class)->rootCategories()
            ->map(fn ($c) => ['label' => $c->t('name'), 'value' => $counts[$c->id] ?? 0])
            ->filter(fn ($s) => $s['value'] > 0)->values()->all();
    }

    /** Daily series for the last N days: ['labels' => [...], 'views' => [...], 'searches' => [...]] */
    public function dailyActivity(int $days = 30): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $views = DB::table('business_events')->where('type', 'view')->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as d, count(*) as c")->groupBy('d')->pluck('c', 'd');
        $searches = DB::table('search_events')->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as d, count(*) as c")->groupBy('d')->pluck('c', 'd');

        $labels = $v = $s = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);
            $labels[] = $day->locale(app()->getLocale())->isoFormat('D MMM');
            $v[] = (int) ($views[$day->toDateString()] ?? 0);
            $s[] = (int) ($searches[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'views' => $v, 'searches' => $s];
    }

    public function recentUsers(int $limit = 5): Collection
    {
        return User::query()->latest()->limit($limit)->get();
    }

    public function recentSubmissions(int $limit = 6): Collection
    {
        return Business::query()->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id', 'owner:id,name'])
            ->whereNotNull('submitted_at')->latest('submitted_at')->limit($limit)->get();
    }

    public function recentActivity(int $limit = 8): Collection
    {
        return AuditLog::query()->with('user:id,name')->latest('created_at')->latest('id')->limit($limit)->get();
    }

    public function topSearches(int $days = 30, int $limit = 8): Collection
    {
        return DB::table('search_events')->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('normalized_query')
            ->selectRaw('normalized_query as q, count(*) as total, round(avg(results_count)) as avg_results')
            ->groupBy('normalized_query')->orderByDesc('total')->limit($limit)->get();
    }
}
