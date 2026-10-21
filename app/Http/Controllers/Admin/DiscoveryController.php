<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrapeSource;
use App\Services\AuditLogger;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\DiscoveryRunner;
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
    public const MODES = ['sitemap', 'links', 'urls'];

    public function __construct(private readonly DiscoveryRunner $runner, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $counts = DB::table('scrape_urls')->selectRaw('scrape_source_id, status, count(*) as total')
            ->groupBy('scrape_source_id', 'status')->get()
            ->groupBy('scrape_source_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all());

        return view('admin.discovery.index', [
            'sources' => ScrapeSource::query()->orderByDesc('is_enabled')->orderBy('name')->get(),
            'counts' => $counts,
        ]);
    }

    public function create(): View
    {
        return view('admin.discovery.form', ['source' => new ScrapeSource(['discovery' => 'sitemap', 'is_enabled' => true])]);
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

    /** Runs one short crawl now (about 20 seconds) and reports what it found. */
    public function run(ScrapeSource $source): RedirectResponse
    {
        @set_time_limit(60);
        $result = $this->runner->run($source, 20.0);

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
            'start_urls' => [Rule::requiredIf(fn () => $request->input('discovery') === 'urls'), 'nullable', 'string', 'max:20000'],
            'url_pattern' => [Rule::requiredIf(fn () => $request->input('discovery') === 'links'), 'nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (Urls::pattern($value) === null) {
                    $fail(__('discovery.errors.pattern'));
                }
            }],
            'selectors' => ['nullable', 'string', 'max:5000'],
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
            $url = Urls::normalize($line);
            if ($url === null || ! UrlGuard::sameSite($url, $data['base_url'])) {
                throw ValidationException::withMessages(['start_urls' => __('discovery.errors.start_urls')]);
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
