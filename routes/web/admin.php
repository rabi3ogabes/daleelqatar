<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
| Administration dashboard. Access requires the "admin.access" permission;
| every module additionally checks its own permission.
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'can:admin.access'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::middleware('can:businesses.view')->group(function () {
        Route::get('/businesses/export', [Admin\BusinessController::class, 'export'])->middleware('can:businesses.export')->name('businesses.export');
        Route::get('/businesses', [Admin\BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [Admin\BusinessController::class, 'create'])->middleware('can:businesses.create')->name('businesses.create');
        Route::post('/businesses', [Admin\BusinessController::class, 'store'])->middleware('can:businesses.create')->name('businesses.store');
        Route::get('/businesses/{business:id}', [Admin\BusinessController::class, 'show'])->withTrashed()->name('businesses.show');
        Route::get('/businesses/{business:id}/edit', [Admin\BusinessController::class, 'edit'])->middleware('can:businesses.update')->name('businesses.edit');
        Route::put('/businesses/{business:id}', [Admin\BusinessController::class, 'update'])->middleware('can:businesses.update')->name('businesses.update');
        Route::delete('/businesses/{business:id}', [Admin\BusinessController::class, 'destroy'])->middleware('can:businesses.delete')->name('businesses.destroy');
        Route::post('/businesses/{business:id}/restore-deleted', [Admin\BusinessController::class, 'restoreDeleted'])->withTrashed()->middleware('can:businesses.delete')->name('businesses.restore-deleted');
        Route::post('/businesses/{business:id}/status', [Admin\BusinessController::class, 'status'])->middleware('can:businesses.moderate')->name('businesses.status');
        Route::post('/businesses/{business:id}/feature', [Admin\BusinessController::class, 'feature'])->middleware('can:businesses.feature')->name('businesses.feature');
        Route::post('/businesses/{business:id}/verify', [Admin\BusinessController::class, 'verify'])->middleware('can:businesses.verify')->name('businesses.verify');
        Route::post('/businesses/{business:id}/owner', [Admin\BusinessController::class, 'owner'])->middleware('can:businesses.update')->name('businesses.owner');
        Route::post('/businesses/{business:id}/logo', [Admin\BusinessController::class, 'logo'])->middleware('can:businesses.update')->name('businesses.logo');
        Route::post('/businesses/{business:id}/cover', [Admin\BusinessController::class, 'cover'])->middleware('can:businesses.update')->name('businesses.cover');
        Route::post('/businesses/{business:id}/gallery', [Admin\BusinessController::class, 'gallery'])->middleware('can:businesses.update')->name('businesses.gallery');
        Route::delete('/businesses/{business:id}/gallery/{image}', [Admin\BusinessController::class, 'destroyImage'])->middleware('can:businesses.update')->name('businesses.gallery.destroy');
    });

    Route::middleware('can:imports.manage')->group(function () {
        Route::get('/imports', [Admin\ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [Admin\ImportController::class, 'store'])->middleware('throttle:20,1')->name('imports.store');
        Route::post('/imports/sign', [Admin\ImportController::class, 'sign'])->middleware('throttle:20,1')->name('imports.sign');
        Route::get('/imports/{batch:uuid}', [Admin\ImportController::class, 'show'])->name('imports.show');
        Route::post('/imports/{batch:uuid}/uploaded', [Admin\ImportController::class, 'uploaded'])->name('imports.uploaded');
        Route::post('/imports/{batch:uuid}/start', [Admin\ImportController::class, 'start'])->name('imports.start');
        Route::post('/imports/{batch:uuid}/process', [Admin\ImportController::class, 'process'])->name('imports.process');
        Route::post('/imports/{batch:uuid}/cancel', [Admin\ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('/imports/{batch:uuid}/report', [Admin\ImportController::class, 'report'])->name('imports.report');
        Route::delete('/imports/{batch:uuid}', [Admin\ImportController::class, 'destroy'])->name('imports.destroy');

        Route::get('/discovery', [Admin\DiscoveryController::class, 'index'])->name('discovery.index');
        Route::get('/discovery/create', [Admin\DiscoveryController::class, 'create'])->name('discovery.create');
        Route::post('/discovery', [Admin\DiscoveryController::class, 'store'])->name('discovery.store');
        Route::get('/discovery/{source}/edit', [Admin\DiscoveryController::class, 'edit'])->name('discovery.edit');
        Route::put('/discovery/{source}', [Admin\DiscoveryController::class, 'update'])->name('discovery.update');
        Route::delete('/discovery/{source}', [Admin\DiscoveryController::class, 'destroy'])->name('discovery.destroy');
        Route::post('/discovery/{source}/run', [Admin\DiscoveryController::class, 'run'])->middleware('throttle:30,1')->name('discovery.run');
        Route::post('/discovery/test', [Admin\DiscoveryController::class, 'test'])->middleware('throttle:30,1')->name('discovery.test');
    });

    Route::middleware('can:claims.review')->group(function () {
        Route::get('/claims', [Admin\ClaimController::class, 'index'])->name('claims.index');
        Route::get('/claims/{claim}', [Admin\ClaimController::class, 'show'])->name('claims.show');
        Route::post('/claims/{claim}/decide', [Admin\ClaimController::class, 'decide'])->name('claims.decide');
        Route::get('/claims/{claim}/proof', [Admin\ClaimController::class, 'proof'])->name('claims.proof');
    });

    Route::middleware('can:businesses.verify')->group(function () {
        Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [Admin\VerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{verification}/decide', [Admin\VerificationController::class, 'decide'])->name('verifications.decide');
        Route::get('/verifications/{verification}/document', [Admin\VerificationController::class, 'document'])->name('verifications.document');
    });

    Route::middleware('can:categories.manage')->group(function () {
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::post('/categories/{category}/move/{direction}', [Admin\CategoryController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('categories.move');
    });

    Route::middleware('can:locations.manage')->group(function () {
        Route::resource('municipalities', Admin\MunicipalityController::class)->except('show');
        Route::get('/municipalities/{municipality}/areas/create', [Admin\AreaController::class, 'create'])->name('areas.create');
        Route::post('/municipalities/{municipality}/areas', [Admin\AreaController::class, 'store'])->name('areas.store');
        Route::get('/areas/{area}/edit', [Admin\AreaController::class, 'edit'])->name('areas.edit');
        Route::put('/areas/{area}', [Admin\AreaController::class, 'update'])->name('areas.update');
        Route::delete('/areas/{area}', [Admin\AreaController::class, 'destroy'])->name('areas.destroy');
    });

    Route::middleware('can:users.view')->group(function () {
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}/roles', [Admin\UserController::class, 'roles'])->name('users.roles');
        Route::post('/users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('/users/{user}/password-reset', [Admin\UserController::class, 'sendPasswordReset'])->middleware('throttle:password')->name('users.password-reset');
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('can:reviews.moderate')->group(function () {
        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/moderate', [Admin\ReviewController::class, 'moderate'])->withTrashed()->name('reviews.moderate');
        Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    Route::middleware('can:reports.handle')->group(function () {
        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/listing/{report}', [Admin\ReportController::class, 'resolveListing'])->name('reports.listing.resolve');
        Route::post('/reports/review/{report}', [Admin\ReportController::class, 'resolveReview'])->name('reports.review.resolve');
    });

    Route::middleware('can:offers.moderate')->group(function () {
        Route::get('/offers', [Admin\OfferController::class, 'index'])->name('offers.index');
        Route::post('/offers/{offer}/moderate', [Admin\OfferController::class, 'moderate'])->name('offers.moderate');
    });
    Route::middleware('can:offers.manage')->group(function () {
        Route::get('/offers/create', [Admin\OfferController::class, 'create'])->name('offers.create');
        Route::post('/offers', [Admin\OfferController::class, 'store'])->name('offers.store');
        Route::get('/offers/{offer}/edit', [Admin\OfferController::class, 'edit'])->name('offers.edit');
        Route::put('/offers/{offer}', [Admin\OfferController::class, 'update'])->name('offers.update');
        Route::delete('/offers/{offer}', [Admin\OfferController::class, 'destroy'])->name('offers.destroy');
    });

    Route::middleware('can:content.manage')->group(function () {
        Route::get('/pages', [Admin\PageController::class, 'index'])->name('pages.index');
        Route::get('/pages/{page}/edit', [Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');
        Route::resource('faqs', Admin\FaqController::class)->except('show');
        Route::resource('banners', Admin\BannerController::class)->except('show');
        Route::get('/homepage', [Admin\HomepageController::class, 'edit'])->name('homepage.edit');
        Route::put('/homepage', [Admin\HomepageController::class, 'update'])->name('homepage.update');
    });

    Route::middleware('can:messages.manage')->group(function () {
        Route::get('/messages', [Admin\MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [Admin\MessageController::class, 'show'])->name('messages.show');
        Route::put('/messages/{message}', [Admin\MessageController::class, 'update'])->name('messages.update');
        Route::delete('/messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings/{group?}', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings/{group}', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('/analytics', [Admin\AnalyticsController::class, 'index'])->middleware('can:analytics.view')->name('analytics.index');

    Route::middleware('can:audit.view')->group(function () {
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit-logs/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');
    });

    Route::get('/notifications', [Admin\NotificationController::class, 'index'])->name('notifications');
});
