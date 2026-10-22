<?php

use App\Http\Controllers\Account\BusinessController;
use App\Http\Controllers\Account\BusinessMediaController;
use App\Http\Controllers\Account\ClaimController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\InsightsController;
use App\Http\Controllers\Account\LeadController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\OfferController;
use App\Http\Controllers\Account\QuoteController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\Account\SpecialHoursController;
use App\Http\Controllers\Account\VerificationController;
use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

/*
| Business-owner & member dashboard. Every business route is ownership-checked
| through BusinessPolicy; {business} and {offer} are bound by UUID.
*/
Route::prefix('account')->name('account.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [BusinessController::class, 'store'])->middleware('throttle:submissions')->name('businesses.store');
    Route::get('/businesses/{business:uuid}', [BusinessController::class, 'show'])->name('businesses.show');
    Route::get('/businesses/{business:uuid}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
    Route::put('/businesses/{business:uuid}', [BusinessController::class, 'update'])->name('businesses.update');
    Route::delete('/businesses/{business:uuid}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
    Route::post('/businesses/{business:uuid}/submit', [BusinessController::class, 'submit'])->name('businesses.submit');
    Route::post('/businesses/{business:uuid}/archive', [BusinessController::class, 'archive'])->name('businesses.archive');
    Route::post('/businesses/{business:uuid}/unarchive', [BusinessController::class, 'unarchive'])->name('businesses.unarchive');

    Route::middleware('throttle:uploads')->group(function () {
        Route::post('/businesses/{business:uuid}/logo', [BusinessMediaController::class, 'logo'])->name('businesses.logo');
        Route::delete('/businesses/{business:uuid}/logo', [BusinessMediaController::class, 'removeLogo'])->name('businesses.logo.destroy');
        Route::post('/businesses/{business:uuid}/cover', [BusinessMediaController::class, 'cover'])->name('businesses.cover');
        Route::delete('/businesses/{business:uuid}/cover', [BusinessMediaController::class, 'removeCover'])->name('businesses.cover.destroy');
        Route::post('/businesses/{business:uuid}/gallery', [BusinessMediaController::class, 'gallery'])->name('businesses.gallery.store');
        Route::delete('/businesses/{business:uuid}/gallery/{image}', [BusinessMediaController::class, 'destroyImage'])->name('businesses.gallery.destroy');
        Route::post('/businesses/{business:uuid}/gallery/reorder', [BusinessMediaController::class, 'reorder'])->name('businesses.gallery.reorder');
    });

    Route::get('/businesses/{business:uuid}/poster', [QrController::class, 'poster'])->name('businesses.poster');
    Route::get('/businesses/{business:uuid}/qr.svg', [QrController::class, 'svg'])->name('businesses.qr');
    Route::get('/businesses/{business:uuid}/insights', [InsightsController::class, 'show'])->name('businesses.insights');
    Route::get('/businesses/{business:uuid}/special-hours', [SpecialHoursController::class, 'edit'])->name('businesses.special-hours');
    Route::put('/businesses/{business:uuid}/special-hours/{period}', [SpecialHoursController::class, 'update'])->whereNumber('period')->name('businesses.special-hours.update');
    Route::get('/businesses/{business:uuid}/verification', [VerificationController::class, 'create'])->name('businesses.verification');
    Route::post('/businesses/{business:uuid}/verification', [VerificationController::class, 'store'])->middleware('throttle:submissions')->name('businesses.verification.store');

    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('/offers/create', [OfferController::class, 'create'])->name('offers.create');
    Route::post('/offers', [OfferController::class, 'store'])->middleware('throttle:submissions')->name('offers.store');
    Route::get('/offers/{offer:uuid}/edit', [OfferController::class, 'edit'])->name('offers.edit');
    Route::put('/offers/{offer:uuid}', [OfferController::class, 'update'])->name('offers.update');
    Route::delete('/offers/{offer:uuid}', [OfferController::class, 'destroy'])->name('offers.destroy');

    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review:uuid}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
    Route::get('/my-reviews', [ReviewController::class, 'mine'])->name('my-reviews');
    Route::delete('/my-reviews/{review:uuid}', [ReviewController::class, 'destroy'])->name('my-reviews.destroy');

    // Quote requests the user sent, and the ones their businesses received.
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{quote:uuid}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/quotes/{quote:uuid}/close', [QuoteController::class, 'close'])->name('quotes.close');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('leads.show');
    Route::post('/leads/{lead}/reply', [LeadController::class, 'reply'])->whereNumber('lead')->middleware('throttle:30,1')->name('leads.reply');
    Route::post('/leads/{lead}/decline', [LeadController::class, 'decline'])->whereNumber('lead')->name('leads.decline');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites');
    Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
    Route::post('/claims/{claim:uuid}/cancel', [ClaimController::class, 'cancel'])->name('claims.cancel');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');
    Route::put('/settings/preferences', [SettingsController::class, 'preferences'])->name('settings.preferences');
    Route::delete('/settings', [SettingsController::class, 'destroy'])->name('settings.destroy');
});
