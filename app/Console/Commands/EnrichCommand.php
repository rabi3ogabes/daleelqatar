<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Support\Enrichment\Enricher;
use Illuminate\Console\Command;
use Throwable;

class EnrichCommand extends Command
{
    protected $signature = 'daleel:enrich
        {--business= : Enrich one listing (ID)}
        {--limit=25 : Listings to process}
        {--names : Only suggest Arabic names (fast, batches of 40)}
        {--no-google : Skip Google Maps}
        {--no-website : Skip the company website}
        {--no-ai : Skip AI suggestions}
        {--seconds=0 : Stop after this many seconds (0 = no limit)}';

    protected $description = 'Find missing listing details on Google Maps and company websites; suggestions wait for admin approval.';

    public function handle(Enricher $enricher): int
    {
        $started = microtime(true);
        $budget = (float) $this->option('seconds');
        $query = Business::query()->whereNull('deleted_at')->where('is_demo', false);

        if ($id = $this->option('business')) {
            $query->whereKey((int) $id);
        } elseif ($this->option('names')) {
            $query->whereRaw("coalesce(name_ar, '') !~ '[\\u0600-\\u06FF]'");
        } else {
            $query->whereNull('enriched_at');
        }
        $businesses = $query->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();

        if ($this->option('names')) {
            $count = 0;
            foreach ($businesses->chunk(40) as $chunk) {
                $count += $enricher->translateNames($chunk);
            }
            $this->info("✔ {$count} Arabic name suggestions waiting for review.");

            return self::SUCCESS;
        }

        $options = ['google' => ! $this->option('no-google'), 'website' => ! $this->option('no-website'), 'ai' => ! $this->option('no-ai')];
        $pending = 0;
        foreach ($businesses as $business) {
            if ($budget > 0 && microtime(true) - $started > $budget) {
                break;
            }
            try {
                $suggestion = $enricher->run($business, $options);
                $pending += $suggestion->status === 'pending' ? 1 : 0;
                $this->line(sprintf('  %s %s — %d field(s)', $suggestion->status === 'pending' ? '<info>●</info>' : '<comment>○</comment>', $business->name_en, count($suggestion->fields)));
            } catch (Throwable $e) {
                report($e);
                $this->error("  ✖ {$business->name_en}: {$e->getMessage()}");
            }
        }
        $this->info("✔ {$pending} listing(s) have suggestions waiting for review.");

        return self::SUCCESS;
    }
}
