<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\SeoController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmSitemapCommand extends Command
{
    protected $signature = 'daleel:sitemap';

    protected $description = 'Rebuild and cache the XML sitemap (Arabic and English alternates).';

    public function handle(): int
    {
        if (! setting('seo.sitemap_enabled', true)) {
            $this->warn('The sitemap is disabled in the SEO settings.');

            return self::SUCCESS;
        }

        Cache::forget('sitemap.xml');
        $response = app(SeoController::class)->sitemap();
        $urls = substr_count((string) $response->getContent(), '<url>');
        $this->info("✔ Sitemap cached with {$urls} URLs.");

        return self::SUCCESS;
    }
}
