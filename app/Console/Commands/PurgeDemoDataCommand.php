<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Sitemap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeDemoDataCommand extends Command
{
    protected $signature = 'daleel:purge-demo {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete every development sample listing (is_demo = true) and its related records.';

    public function handle(MediaService $media, AuditLogger $audit): int
    {
        $count = Business::query()->withTrashed()->where('is_demo', true)->count();
        if ($count === 0) {
            $this->info('No sample listings found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$count} sample listing(s)?")) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($media) {
            Business::query()->withTrashed()->where('is_demo', true)->with('images')->chunkById(100, function ($chunk) use ($media) {
                foreach ($chunk as $business) {
                    $media->delete($business->logo_path);
                    $media->delete($business->cover_path);
                    foreach ($business->images as $image) {
                        $media->delete($image->path, $image->disk);
                    }
                    $business->forceDelete(); // related rows cascade at database level
                }
            });
        });

        $audit->log('business.demo_purged', null, [], ['deleted' => $count], ['via' => 'console']);
        Directory::flushCounts();
        NavigationData::flush();
        Sitemap::flush();

        $this->info("✔ Deleted {$count} sample listing(s).");

        return self::SUCCESS;
    }
}
