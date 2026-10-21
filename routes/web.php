<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Non-localized endpoints
|--------------------------------------------------------------------------
*/
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/manifest.webmanifest', [SeoController::class, 'manifest'])->name('manifest');
Route::get('/cron/maintenance', [CronController::class, 'maintenance'])
    ->middleware('throttle:10,1')
    ->name('cron.maintenance');

/*
|--------------------------------------------------------------------------
| Localized web routes
|--------------------------------------------------------------------------
| Arabic (default) has no prefix; English lives under /en. Each route is
| registered once per language as "ar.<name>" / "en.<name>". Use the bare
| name — route('home') — and the active language is chosen automatically.
*/
foreach (['ar' => '', 'en' => 'en'] as $locale => $prefix) {
    Route::prefix($prefix)
        ->name($locale.'.')
        ->middleware('locale:'.$locale)
        ->group(function () {
            require __DIR__.'/web/public.php';
            require __DIR__.'/web/auth.php';
            require __DIR__.'/web/account.php';
            require __DIR__.'/web/admin.php';
        });
}
