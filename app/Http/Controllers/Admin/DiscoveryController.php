<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ScrapeSource;
use App\Services\AuditLogger;
use App\Support\Deadline;
use App\Support\Discovery\Discoverer;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\DiscoveryRunner;
use App\Support\Discovery\Fetcher;
use App\Support\Discovery\Presets;
use App\Support\Discovery\SourceAnalyzer;
use App\Support\Discovery\UrlGuard;
use App\Support\Discovery\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Directory websites the crawler collects new companies from. Each source is
 * crawled politely (robots.txt, delays) by the daily cron or on demand, and
 * every company found goes through the same de-duplicating importer.
 */
class DiscoveryController extends Controller
{
    public const MODES = ['sitemap', 'pages', 'links', 'urls'];

    public function __construct(private readonly DiscoveryRunner $runner, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $counts = DB::table('scrape_urls')->selectRaw('scrape_source_id, status, count(*) as total')
            ->groupBy('scrape_source_id', 'status')->get()
            ->groupBy('scrape_source_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all());

        // Companies each source added in the last 7 days.
        $recent = DB::table('scrape_urls')->where('status', 'done')->where('message', 'like', 'created%')
            ->where('fetched_at', '>=', now()->subDays(7))
            ->selectRaw('scrape_source_id, count(*) as total')->groupBy('scrape_source_id')->pluck('total', 'scrape_source_id')
            ->map(fn ($n) => (int) $n);

        $sources = ScrapeSource::query()->orderByDesc('is_enabled')->orderBy('name')->get();

        return view('admin.discovery.index', [
            'sources' => $sources,
            'counts' => $counts,
            'recent' => $recent,
            'presets' => array_filter(Presets::ALL, fn ($p) => ! $sources->contains(fn ($s) => UrlGuard::sameSite($s->base_url, $p['base_url']))),
        ]);
    }

    public function create(Request $request): View
    {
        $preset = Presets::get($request->query('preset'));
        $source = new ScrapeSource(['discovery' => 'sitemap', 'is_enabled' => true]);
        if ($preset !== null) {
            $source->fill(Arr::except($preset, ['settings']));
            $source->settings = $preset['settings'];
        }

        return view('admin.discovery.form', ['source' => $source, 'preset' => $preset !== null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $source = new ScrapeSource;
        $source->fill($this->validated($request));
        $source->slug = $this->uniqueSlug($source->name);
        $source->save();

        $this->audit->log('discovery.created', null, [], $source->only(['name', 'base_url', 'discovery', 'is_enabled']), ['source_id' => $source->id]);

        return redirect()->route('admin.discovery.index')->with('success', __('discovery.saved'));
    }

    public function edit(ScrapeSource $source): View
    {
        return view('admin.discovery.form', ['source' => $source]);
    }

    public function update(Request $request, ScrapeSource $source): RedirectResponse
    {
        $source->fill($this->validated($request, $source));
        // Restart discovery when the place to look for pages changed.
        if ($source->isDirty(['base_url', 'discovery', 'start_urls', 'url_pattern'])) {
            $source->forceFill(['last_discovered_at' => null, 'stats' => array_diff_key((array) $source->stats, array_flip(['frontier', 'visited']))]);
        }
        $changes = array_keys(Arr::except($source->getDirty(), ['updated_at', 'stats', 'last_discovered_at']));
        $source->save();
        if ($changes !== []) {
            $this->audit->log('discovery.updated', null, [], $source->only($changes), ['source_id' => $source->id]);
        }

        return redirect()->route('admin.discovery.index')->with('success', __('discovery.saved'));
    }

    public function destroy(ScrapeSource $source): RedirectResponse
    {
        $this->audit->log('discovery.deleted', null, $source->only(['name', 'base_url']), [], ['source_id' => $source->id]);
        $source->delete();

        return redirect()->route('admin.discovery.index')->with('success', __('discovery.deleted'));
    }

    /**
     * Runs one short crawl now (about 20 seconds) and reports what it found. The live runner on the
     * sources page calls it as JSON again and again while there is work left.
     */
    public function run(Request $request, ScrapeSource $source): RedirectResponse|JsonResponse
    {
        @set_time_limit(60);
        Deadline::start(50);
        $result = $this->runner->run($source, $request->wantsJson() ? 30.0 : 20.0);

        if ($request->wantsJson()) {
            $source->refresh();
            $pending = $source->urls()->where('status', 'pending')->count();
            $listing = ! empty(((array) $source->stats)['frontier']);

            return response()->json([
                'status' => $result['status'] === 'ok' || $result['status'] === 'busy' ? $result['status'] : 'error',
                'message' => match (true) {
                    $result['status'] === 'busy' => __('discovery.busy'),
                    $result['status'] !== 'ok' => __('discovery.run_failed'),
                    default => null,
                },
                'run' => Arr::only($result, DiscoveryRunner::COUNTERS),
                'pending' => $pending,
                'listing' => $listing,
                'more' => $result['status'] !== 'busy' && ($pending > 0 || $listing),
                'total_created' => (int) (((array) $source->stats)['created'] ?? 0),
            ], $result['status'] === 'ok' || $result['status'] === 'busy' ? 200 : 500);
        }

        if ($result['status'] === 'busy') {
            return back()->with('warning', __('discovery.busy'));
        }
        if ($result['status'] !== 'ok') {
            return back()->with('error', __('discovery.run_failed'));
        }

        return back()->with('success', __('discovery.run_done', [
            'discovered' => $result['discovered'], 'processed' => $result['processed'],
            'created' => $result['created'], 'merged' => $result['merged'],
        ]));
    }

    /** Works out the method, start address and company-page pattern from a website address. */
    public function analyze(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'url:http,https', 'max:300']]);
        @set_time_limit(60);
        Deadline::start(45);

        try {
            UrlGuard::check($data['url']);
            $result = (new SourceAnalyzer(new Fetcher(800, 10)))->analyze($data['url']);
        } catch (DiscoveryException $e) {
            return response()->json(['ok' => false, 'message' => self::reason($e->getMessage())], 422);
        } finally {
            Deadline::clear();
        }

        return response()->json([
            'ok' => $result['mode'] !== null,
            'message' => $result['mode'] === null ? __('discovery.analyze_none') : null,
            'base_url' => $result['base_url'],
            'mode' => $result['mode'],
            'mode_label' => $result['mode'] ? __('discovery.modes.'.$result['mode']) : null,
            'start_urls' => implode("\n", $result['start_urls']),
            'url_pattern' => $result['url_pattern'],
            'last_page' => $result['last_page'],
            'detail_count' => $result['detail_count'],
            'steps' => array_map(fn ($step) => ['label' => __('discovery.analyze_steps.'.$step['key']), 'ok' => $step['ok'], 'detail' => $step['detail'] ?? null], $result['steps']),
            'sample' => $result['sample'],
        ]);
    }

    /** The companies a source added to the directory, newest first. */
    public function newCompanies(Request $request, ScrapeSource $source): View
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30, 90], true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days);

        $urls = $source->urls()->where('status', 'done')->where('message', 'like', 'created%')->whereNotNull('business_id')
            ->where('fetched_at', '>=', $since)->orderByDesc('fetched_at')->paginate(25)->withQueryString();
        $businesses = Business::withTrashed()->with(['category', 'municipality', 'area'])->whereIn('id', $urls->pluck('business_id'))->get()->keyBy('id');

        return view('admin.discovery.new', [
            'source' => $source,
            'urls' => $urls,
            'businesses' => $businesses,
            'days' => $days,
            'waiting' => $source->urls()->where('status', 'pending')->count(),
            'completed' => $source->urls()->where('status', 'done')->where('message', 'like', 'merged%')->where('fetched_at', '>=', $since)->count(),
        ]);
    }

    /** Shows what the crawler would read from one page (nothing is saved). */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2000'],
            'selectors' => ['nullable', 'string', 'max:5000'],
            'source_id' => ['nullable', 'integer', 'exists:scrape_sources,id'],
        ]);
        $source = isset($data['source_id']) ? ScrapeSource::query()->find($data['source_id']) : null;
        $selectors = array_key_exists('selectors', $data) ? DiscoveryRunner::parseSelectors($data['selectors']) : (array) ($source?->selectors ?? []);

        try {
            $result = $this->runner->test($data['url'], $selectors, $source);
        } catch (DiscoveryException $e) {
            return response()->json(['ok' => false, 'message' => self::reason($e->getMessage())], 422);
        }

        $record = $result['record'];

        return response()->json([
            'ok' => $record !== null,
            'url' => $result['url'],
            'method' => $result['method'],
            'message' => $record === null ? __('discovery.reasons.no_business_data') : null,
            'fields' => $record === null ? [] : array_filter([
                'name_en' => $record['name_en'],
                'name_ar' => $record['name_ar'],
                'categories' => implode(' · ', $record['categories']),
                'phone' => implode(', ', array_filter([$record['phone'], $record['whatsapp'], ...$record['extra_phones']])),
                'email' => implode(', ', array_filter([$record['email'], ...$record['extra_emails']])),
                'website' => $record['website'],
                'social' => implode(' ', $record['social']),
                'address' => $record['address'],
                'city' => $record['city'],
                'coordinates' => $record['latitude'] !== null ? $record['latitude'].', '.$record['longitude'] : null,
                'hours' => $record['hours'],
                'description' => $record['description'] ? Str::limit($record['description'], 300) : null,
                'logo' => $record['logo_url'] ?? null,
                'image' => $record['image_url'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    public static function reason(?string $code): string
    {
        $key = 'discovery.reasons.'.Str::before((string) $code, ':');

        return Lang::has($key) ? __($key) : Str::limit((string) $code, 90);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ScrapeSource $source = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url:http,https', 'max:300', function ($attr, $value, $fail) {
                try {
                    UrlGuard::check((string) $value);
                } catch (DiscoveryException $e) {
                    $fail(self::reason($e->getMessage()));
                }
            }],
            'discovery' => ['required', Rule::in(self::MODES)],
            'start_urls' => [Rule::requiredIf(fn () => in_array($request->input('discovery'), ['urls', 'pages'], true)), 'nullable', 'string', 'max:20000'],
            'url_pattern' => [Rule::requiredIf(fn () => in_array($request->input('discovery'), ['links', 'pages'], true)), 'nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (Urls::pattern($value) === null) {
                    $fail(__('discovery.errors.pattern'));
                }
            }],
            'selectors' => ['nullable', 'string', 'max:5000'],
            'search_url' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) use ($request) {
                $url = Urls::normalize(str_replace('{query}', 'test', (string) $value));
                if (! str_contains((string) $value, '{query}') || $url === null || ! UrlGuard::sameSite($url, (string) $request->input('base_url'))) {
                    $fail(__('discovery.errors.search_url'));
                }
            }],
            'delay_ms' => ['required', 'integer', 'min:1000', 'max:60000'],
            'max_pages_per_run' => ['required', 'integer', 'min:1', 'max:200'],
            'rediscover_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'publish' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        // Every start URL must be on the source's own site.
        $starts = [];
        foreach (preg_split('/\R/', (string) ($data['start_urls'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($data['discovery'] === 'pages') {
                // A template: "{page}" is kept as typed and replaced by 1, 2, 3… when crawling.
                $url = str_contains($line, '{page}') ? Urls::normalize(Discoverer::pageUrl($line, 987654321)) : null;
                $url = $url !== null ? str_replace('987654321', '{page}', $url) : null;
                if ($url === null || ! UrlGuard::sameSite(Discoverer::pageUrl($url, 1), $data['base_url'])) {
                    throw ValidationException::withMessages(['start_urls' => __('discovery.errors.page_template')]);
                }
            } else {
                $url = Urls::normalize($line);
                if ($url === null || ! UrlGuard::sameSite($url, $data['base_url'])) {
                    throw ValidationException::withMessages(['start_urls' => __('discovery.errors.start_urls')]);
                }
            }
            $starts[] = $url;
        }

        return [
            'name' => $data['name'],
            'base_url' => rtrim($data['base_url'], '/'),
            'discovery' => $data['discovery'],
            'start_urls' => implode("\n", array_unique(array_slice($starts, 0, 200))),
            'url_pattern' => $data['url_pattern'] ?? null,
            'selectors' => DiscoveryRunner::parseSelectors($data['selectors'] ?? '') ?: null,
            'settings' => array_merge((array) ($source?->settings ?? []), [
                'delay_ms' => (int) $data['delay_ms'],
                'max_pages_per_run' => (int) $data['max_pages_per_run'],
                'rediscover_hours' => (int) $data['rediscover_hours'],
                'publish' => $request->boolean('publish'),
                'search_url' => ($data['search_url'] ?? null) ?: null,
            ]),
            'is_enabled' => $request->boolean('is_enabled'),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'source', 70, '');
        $slug = $base;
        for ($i = 2; ScrapeSource::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
