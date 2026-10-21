<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Sitemap;
use Illuminate\Console\Command;

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

        $urls = Sitemap::warm();
        $this->info('✔ Sitemap cached with '.$urls.' URLs in '.count(Sitemap::parts()).' files.');

        return self::SUCCESS;
    }
}
