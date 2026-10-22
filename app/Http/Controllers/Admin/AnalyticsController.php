<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessEventType;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Support\Analytics\SiteAnalytics;
use App\Support\Analytics\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Reports built exclusively from recorded events and records. */
class AnalyticsController extends Controller
{
    public const RANGES = [7, 30, 90];

    public function __construct(private readonly SiteAnalytics $site) {}

    /** Visitors (who, where from, which pages, on what device) — the default view. */
    public function index(Request $request): View
    {
        if ($request->query('tab') === 'directory') {
            return $this->directory($request);
        }
        $range = in_array((string) $request->query('range'), SiteAnalytics::RANGES, true) ? (string) $request->query('range') : '7';

        return view('admin.analytics.visitors', [
            'range' => $range,
            'report' => $this->site->overview($range),
            'realtime' => $this->realtimePayload(),
        ]);
    }

    public function realtime(): JsonResponse
    {
        return response()->json($this->realtimePayload())->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function realtimePayload(): array
    {
        $now = $this->site->realtime();
        $total = max(1, $now['active']);

        return [
            'active' => $now['active'],
            'views' => $now['views'],
            'minutes' => $now['minutes'],
            'pages' => $now['pages']->map(fn ($p) => ['title' => self::pageTitle($p->title, $p->path), 'path' => $p->path, 'visitors' => (int) $p->visitors])->all(),
            'countries' => $now['countries']->map(fn ($c) => ['flag' => Visit::flag($c->country), 'name' => Visit::countryName($c->country), 'visitors' => (int) $c->visitors])->all(),
            'devices' => collect(['mobile', 'desktop', 'tablet'])->map(fn ($d) => ['key' => $d, 'label' => __('analytics.devices.'.$d), 'visitors' => (int) ($now['devices'][$d] ?? 0), 'pct' => (int) round(100 * ($now['devices'][$d] ?? 0) / $total)])->all(),
            'sources' => $now['sources']->map(fn ($s) => ['label' => __('analytics.channels.'.$s->channel), 'visitors' => (int) $s->visitors])->all(),
            'at' => now()->format('H:i:s'),
        ];
    }

    /** The page's own title without the site name ("Pearl Coffee | Daleel Qatar" → "Pearl Coffee"). */
    public static function pageTitle(?string $title, ?string $path): string
    {
        $title = trim((string) preg_replace('/\s*[|–—-]\s*(دليل قطر|Daleel Qatar)\s*$/u', '', (string) $title));

        return $title !== '' ? $title : (string) $path;
    }

    /** Directory activity: registrations, listings, searches and contact interactions. */
    private function directory(Request $request): View
    {
        $days = in_array($request->integer('days'), self::RANGES, true) ? $request->integer('days') : 30;
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $prevStart = $start->subDays($days);

        $daily = function ($table, ?callable $scope = null) use ($start, $days) {
            $q = DB::table($table)->where('created_at', '>=', $start);
            if ($scope) {
                $scope($q);
            }
            $rows = $q->selectRaw("to_char(created_at, 'YYYY-MM-DD') as d, count(*) as c")->groupBy('d')->pluck('c', 'd');

            return array_map(fn ($i) => (int) ($rows[$start->addDays($i)->toDateString()] ?? 0), range(0, $days - 1));
        };
        $period = function ($table, ?callable $scope = null) use ($start, $prevStart) {
            $base = DB::table($table);
            if ($scope) {
                $scope($base);
            }
            $current = (clone $base)->where('created_at', '>=', $start)->count();
            $previous = (clone $base)->whereBetween('created_at', [$prevStart, $start])->count();

            return ['value' => $current, 'trend' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null];
        };

        $labels = array_map(fn ($i) => $start->addDays($i)->locale(app()->getLocale())->isoFormat('D MMM'), range(0, $days - 1));
        $views = fn ($q) => $q->where('type', BusinessEventType::View->value);

        $summary = [
            'registrations' => $period('users'),
            'submissions' => $period('business_status_changes', fn ($q) => $q->where('to_status', BusinessStatus::Pending->value)),
            'approvals' => $period('business_status_changes', fn ($q) => $q->where('to_status', BusinessStatus::Approved->value)),
            'views' => $period('business_events', $views),
            'searches' => $period('search_events'),
            'offer_views' => $period('business_events', fn ($q) => $q->where('type', BusinessEventType::OfferView->value)),
        ];

        $interactions = DB::table('business_events')->where('created_at', '>=', $start)
            ->whereIn('type', array_map(fn ($t) => $t->value, BusinessEventType::trackable()))
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        $topQueries = DB::table('search_events')->where('created_at', '>=', $start)->whereNotNull('normalized_query')
            ->selectRaw('normalized_query as q, count(*) as total, round(avg(results_count)) as avg_results')
            ->groupBy('normalized_query')->orderByDesc('total')->limit(10)->get();
        $zeroResults = DB::table('search_events')->where('created_at', '>=', $start)->whereNotNull('normalized_query')->where('results_count', 0)
            ->selectRaw('normalized_query as q, count(*) as total')->groupBy('normalized_query')->orderByDesc('total')->limit(10)->get();

        $topBusinesses = DB::table('business_events')->join('businesses', 'businesses.id', '=', 'business_events.business_id')
            ->where('business_events.created_at', '>=', $start)->where('business_events.type', BusinessEventType::View->value)
            ->selectRaw('businesses.id, businesses.name_ar, businesses.name_en, count(*) as total')
            ->groupBy('businesses.id', 'businesses.name_ar', 'businesses.name_en')->orderByDesc('total')->limit(10)->get();

        $topOffers = DB::table('offers')->whereNull('deleted_at')->where('views_count', '>', 0)
            ->orderByDesc('views_count')->limit(5)->get(['id', 'title_ar', 'title_en', 'views_count']);

        return view('admin.analytics.index', [
            'tab' => 'directory',
            'days' => $days,
            'labels' => $labels,
            'summary' => $summary,
            'viewsDaily' => $daily('business_events', $views),
            'searchDaily' => $daily('search_events'),
            'registrationsDaily' => $daily('users'),
            'interactions' => $interactions,
            'topQueries' => $topQueries,
            'zeroResults' => $zeroResults,
            'topBusinesses' => $topBusinesses,
            'topOffers' => $topOffers,
        ]);
    }
}
