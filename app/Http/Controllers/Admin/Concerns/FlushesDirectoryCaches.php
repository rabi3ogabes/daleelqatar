<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\AdminNavigation;
use App\Support\Directory;
use App\Support\NavigationData;
use Illuminate\Support\Facades\Cache;

trait FlushesDirectoryCaches
{
    protected function flushDirectoryCaches(): void
    {
        NavigationData::flush();
        Directory::flushCounts();
        AdminNavigation::flush();
        Cache::forget('sitemap.xml');
    }
}
