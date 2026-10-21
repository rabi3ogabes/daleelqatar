<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ScrapeSource;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\DiscoveryRunner;
use Illuminate\Console\Command;

/** Runs the auto-discovery crawler from the command line (same engine as the daily cron). */
class DiscoverCommand extends Command
{
    protected $signature = 'daleel:discover
        {source? : Slug or id of one source (default: every enabled source)}
        {--seconds=50 : Time budget for the whole run}
        {--test-url= : Only show what would be extracted from this page; nothing is saved}';

    protected $description = 'Collect new companies from the configured directory websites (robots.txt-aware).';

    public function handle(DiscoveryRunner $runner): int
    {
        $source = null;
        if (($key = $this->argument('source')) !== null) {
            $source = ScrapeSource::query()->where('slug', $key)->orWhere('id', ctype_digit((string) $key) ? (int) $key : 0)->first();
            if ($source === null) {
                $this->error("Unknown source: {$key}");

                return self::FAILURE;
            }
        }

        if ($url = $this->option('test-url')) {
            try {
                $result = $runner->test((string) $url, (array) ($source?->selectors ?? []), $source);
            } catch (DiscoveryException $e) {
                $this->error('Could not read the page: '.$e->getMessage());

                return self::FAILURE;
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $sources = $source ? collect([$source]) : ScrapeSource::query()->where('is_enabled', true)->orderByRaw('last_run_at asc nulls first')->get();
        if ($sources->isEmpty()) {
            $this->warn('No enabled sources.');

            return self::SUCCESS;
        }

        $budget = max(5.0, (float) $this->option('seconds'));
        $started = microtime(true);
        $rows = [];
        foreach ($sources->values() as $i => $s) {
            $left = $budget - (microtime(true) - $started);
            if ($left < 5) {
                break;
            }
            $result = $runner->run($s, max(5.0, $left / ($sources->count() - $i)));
            $rows[] = [$s->slug, $result['status'], $result['discovered'], $result['processed'], $result['created'], $result['merged'], $result['skipped'], $result['failed']];
        }

        $this->table(['Source', 'Status', 'Queued', 'Processed', 'Created', 'Merged', 'Skipped', 'Failed'], $rows);

        return self::SUCCESS;
    }
}
