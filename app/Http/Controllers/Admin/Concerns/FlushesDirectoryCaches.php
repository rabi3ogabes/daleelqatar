<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\AdminNavigation;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Sitemap;

trait FlushesDirectoryCaches
{
    protected function flushDirectoryCaches(): void
    {
        NavigationData::flush();
        Directory::flushCounts();
        AdminNavigation::flush();
        Sitemap::flush();
    }
}
