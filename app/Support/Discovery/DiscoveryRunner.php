<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Models\ScrapeSource;
use App\Models\ScrapeUrl;
use App\Support\Import\BusinessImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * One time-boxed crawl of a source: continue (or start) discovery when it is
 * due, then fetch queued detail pages and hand each company to the same
 * de-duplicating importer the spreadsheet import uses.
 */
final class DiscoveryRunner
{
    public const MAX_ATTEMPTS = 3;

    /** Head-room kept for one more request when deciding whether time remains. */
    private const REQUEST_MS = 2500;

    public const COUNTERS = ['discovered', 'processed', 'created', 'merged', 'skipped', 'failed'];

    public function __construct(private readonly BusinessImporter $importer) {}

    /** @return array<string, int|string> counters for this run plus a "status" */
    public function run(ScrapeSource $source, float $seconds = 20.0): array
    {
        $run = array_fill_keys(self::COUNTERS, 0);
        $lock = Cache::lock('discovery-source:'.$source->id, (int) ceil($seconds) + 60);
        if (! $lock->get()) {
            return $run + ['status' => 'busy'];
        }

        $deadline = microtime(true) + $seconds;
        $fetcher = new Fetcher((int) $source->setting('delay_ms', 1500));
        $hasTime = fn (int $waitMs = 0) => (microtime(true) * 1000) + $waitMs + self::REQUEST_MS < $deadline * 1000;
        $status = 'ok';

        try {
            $pending = $source->urls()->where('status', 'pending')->exists();
            $stats = (array) $source->stats;
            $due = ! empty($stats['frontier'])
                || $source->last_discovered_at === null
                || $source->last_discovered_at->lt(now()->subHours(max(1, (int) $source->setting('rediscover_hours', 24))));

            if ($due) {
                // Leave at least half of the time for detail pages when some are already waiting.
                $discoveryDeadline = $pending ? microtime(true) + $seconds / 2 : $deadline;
                $result = (new Discoverer($fetcher))->run($source, fn (int $wait) => $hasTime($wait) && microtime(true) * 1000 + $wait < $discoveryDeadline * 1000);
                $run['discovered'] = $result['queued'];
            }

            $limit = max(1, (int) $source->setting('max_pages_per_run', 40));
            $selectors = (array) ($source->selectors ?? []);
            $options = ['publish' => (bool) $source->setting('publish', false), 'update_existing' => true];

            foreach ($source->urls()->where('status', 'pending')->orderBy('id')->limit($limit)->get() as $url) {
                if (! $hasTime($fetcher->waitFor($url->url))) {
                    break;
                }
                $outcome = $this->process($url, $source, $fetcher, $selectors, $options);
                $run['processed']++;
                $run[$outcome]++;
            }
        } catch (Throwable $e) {
            report($e);
            $status = Str::limit('error: '.$e->getMessage(), 250, '');
        } finally {
            $lock->release();
        }

        $stats = (array) $source->fresh()?->stats;
        foreach (self::COUNTERS as $key) {
            $stats[$key] = (int) ($stats[$key] ?? 0) + $run[$key];
        }
        $stats['last_run'] = $run;
        $source->forceFill([
            'stats' => $stats,
            'last_run_at' => now(),
            'last_status' => $status,
        ])->save();

        return $run + ['status' => $status];
    }

    /** @return string one of created / merged / skipped / failed (for the run counters) */
    private function process(ScrapeUrl $url, ScrapeSource $source, Fetcher $fetcher, array $selectors, array $options): string
    {
        $url->attempts++;
        $url->fetched_at = now();

        try {
            $page = $fetcher->get($url->url, $source->base_url);
            if (! $page->isHtml()) {
                return $this->finish($url, 'skipped', 'not_html');
            }

            $extracted = Extractor::extract($page->body, $page->url, $selectors);
            if ($extracted['record'] === null) {
                return $this->finish($url, 'skipped', 'no_business_data');
            }

            $result = $this->importer->import($extracted['record'], $source->sourceKey(), $options);
            $url->business_id = $result['business_id'];

            return $this->finish($url, 'done', $result['status'].($result['message'] ? ':'.$result['message'] : ''), $result['status']);
        } catch (DiscoveryException $e) {
            if ($e->retryable && $url->attempts < self::MAX_ATTEMPTS) {
                $url->message = $e->getMessage();
                $url->save();

                return 'failed';
            }

            $permanent = in_array($e->getMessage(), ['robots_disallowed', 'other_site', 'redirect_other_site', 'private_address', 'url_not_allowed'], true);

            return $this->finish($url, $permanent ? 'skipped' : 'failed', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->finish($url, $url->attempts < self::MAX_ATTEMPTS ? 'pending' : 'failed', Str::limit('error: '.$e->getMessage(), 250, ''), 'failed');
        }
    }

    private function finish(ScrapeUrl $url, string $status, string $message, ?string $counter = null): string
    {
        $url->forceFill(['status' => $status, 'message' => Str::limit($message, 250, '')])->save();

        return $counter ?? ($status === 'done' ? 'skipped' : ($status === 'pending' ? 'failed' : $status));
    }

    /**
     * Fetches one page and shows what would be imported, without saving anything.
     *
     * @param  array<string, string>  $selectors
     * @return array{url: string, method: string, raw: ?array, record: ?array}
     *
     * @throws DiscoveryException
     */
    public function test(string $url, array $selectors = [], ?ScrapeSource $source = null): array
    {
        $fetcher = new Fetcher(0);
        $page = $fetcher->get($url, $source?->base_url);
        if (! $page->isHtml()) {
            throw new DiscoveryException('not_html');
        }
        $extracted = Extractor::extract($page->body, $page->url, $selectors);

        return ['url' => $page->url] + $extracted;
    }

    /**
     * Parses the "field: selector" lines administrators type in the form.
     *
     * @return array<string, string>
     */
    public static function parseSelectors(?string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$field, $selector] = array_map('trim', explode(':', $line, 2));
            $field = Str::snake(Str::lower($field));
            if (in_array($field, Extractor::FIELDS, true) && $selector !== '') {
                $out[$field] = Str::limit($selector, 300, '');
            }
        }

        return $out;
    }

    /** @param array<string, string> $selectors */
    public static function selectorsText(array $selectors): string
    {
        return implode("\n", array_map(fn ($f, $s) => $f.': '.$s, array_keys($selectors), $selectors));
    }
}
