<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClaimController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DocsController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\OwnerBusinessController;
use App\Http\Controllers\Api\V1\OwnerMediaController;
use App\Http\Controllers\Api\V1\OwnerOfferController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Middleware\Api\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Daleel Qatar REST API — version 1
|--------------------------------------------------------------------------
| Base URL: /api/v1   ·   Docs: docs/api/README.md, docs/api/openapi.yaml
| Auth: Sanctum bearer tokens (Authorization: Bearer <token>)
| Language: Accept-Language: ar (default) | en
| Identifiers: businesses accept UUID or slug; offers/reviews/claims use UUIDs.
*/

Route::middleware('throttle:api')->group(function () {
    Route::get('/openapi.yaml', [DocsController::class, 'openapi'])->name('openapi');
    Route::get('/settings/app', [SettingsController::class, 'app'])->name('settings.app');

    // ----------------------------------------------------------- Authentication
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:api-auth')->group(function () {
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('email.resend');
        });
    });

    // --------------------------------------------------------- Public directory
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/municipalities', [LocationController::class, 'index'])->name('municipalities.index');
    Route::get('/municipalities/{slug}', [LocationController::class, 'show'])->name('municipalities.show');

    Route::get('/businesses', [BusinessController::class, 'index'])->middleware('throttle:search')->name('businesses.index');
    Route::get('/businesses/featured', [BusinessController::class, 'featured'])->name('businesses.featured');
    Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
    Route::get('/businesses/{business}/reviews', [BusinessController::class, 'reviews'])->name('businesses.reviews');
    Route::post('/businesses/{business}/reports', [BusinessController::class, 'report'])->middleware('throttle:submissions')->name('businesses.reports');
    Route::post('/businesses/{business}/events', [BusinessController::class, 'event'])->middleware('throttle:interactions')->name('businesses.events');

    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('/offers/{offer}', [OfferController::class, 'show'])->name('offers.show');

    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:submissions')->name('contact');

    // ------------------------------------------------------------ Authenticated
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
        Route::put('/me/password', [ProfileController::class, 'password'])->name('me.password');
        Route::delete('/me', [ProfileController::class, 'destroy'])->name('me.destroy');

        Route::get('/me/notifications', [NotificationController::class, 'index'])->name('me.notifications.index');
        Route::post('/me/notifications/read-all', [NotificationController::class, 'readAll'])->name('me.notifications.read-all');
        Route::post('/me/notifications/{id}/read', [NotificationController::class, 'read'])->name('me.notifications.read');

        Route::get('/me/reviews', [ReviewController::class, 'mine'])->name('me.reviews.index');
        Route::delete('/me/reviews/{review}', [ReviewController::class, 'destroy'])->name('me.reviews.destroy');
        Route::get('/me/claims', [ClaimController::class, 'index'])->name('me.claims.index');

        // Everything below requires a verified e-mail address.
        Route::middleware(EnsureEmailIsVerified::class)->group(function () {
            Route::get('/me/favorites', [FavoriteController::class, 'index'])->name('me.favorites.index');
            Route::post('/me/favorites/{business}', [FavoriteController::class, 'store'])->middleware('throttle:interactions')->name('me.favorites.store');
            Route::delete('/me/favorites/{business}', [FavoriteController::class, 'destroy'])->middleware('throttle:interactions')->name('me.favorites.destroy');

            Route::post('/businesses/{business}/reviews', [ReviewController::class, 'store'])->middleware('throttle:submissions')->name('businesses.reviews.store');
            Route::post('/reviews/{review}/report', [ReviewController::class, 'report'])->middleware('throttle:submissions')->name('reviews.report');
            Route::post('/businesses/{business}/claim', [ClaimController::class, 'store'])->middleware('throttle:submissions')->name('businesses.claim');

            // Business-owner management
            Route::get('/me/businesses', [OwnerBusinessController::class, 'index'])->name('me.businesses.index');
            Route::post('/me/businesses', [OwnerBusinessController::class, 'store'])->middleware('throttle:submissions')->name('me.businesses.store');
            Route::get('/me/businesses/{business}', [OwnerBusinessController::class, 'show'])->name('me.businesses.show');
            Route::match(['put', 'patch'], '/me/businesses/{business}', [OwnerBusinessController::class, 'update'])->name('me.businesses.update');
            Route::delete('/me/businesses/{business}', [OwnerBusinessController::class, 'destroy'])->name('me.businesses.destroy');
            Route::post('/me/businesses/{business}/submit', [OwnerBusinessController::class, 'submit'])->name('me.businesses.submit');
            Route::post('/me/businesses/{business}/archive', [OwnerBusinessController::class, 'archive'])->name('me.businesses.archive');
            Route::post('/me/businesses/{business}/unarchive', [OwnerBusinessController::class, 'unarchive'])->name('me.businesses.unarchive');

            Route::middleware('throttle:uploads')->group(function () {
                Route::post('/me/businesses/{business}/logo', [OwnerMediaController::class, 'logo'])->name('me.businesses.logo');
                Route::delete('/me/businesses/{business}/logo', [OwnerMediaController::class, 'destroyLogo'])->name('me.businesses.logo.destroy');
                Route::post('/me/businesses/{business}/cover', [OwnerMediaController::class, 'cover'])->name('me.businesses.cover');
                Route::delete('/me/businesses/{business}/cover', [OwnerMediaController::class, 'destroyCover'])->name('me.businesses.cover.destroy');
                Route::post('/me/businesses/{business}/gallery', [OwnerMediaController::class, 'gallery'])->name('me.businesses.gallery.store');
                Route::put('/me/businesses/{business}/gallery/order', [OwnerMediaController::class, 'reorderGallery'])->name('me.businesses.gallery.order');
                Route::delete('/me/businesses/{business}/gallery/{image}', [OwnerMediaController::class, 'destroyImage'])->whereNumber('image')->name('me.businesses.gallery.destroy');
                Route::post('/me/businesses/{business}/verification', [OwnerMediaController::class, 'verification'])->middleware('throttle:submissions')->name('me.businesses.verification');
            });

            Route::get('/me/reviews/received', [ReviewController::class, 'received'])->name('me.reviews.received');
            Route::post('/me/reviews/{review}/reply', [ReviewController::class, 'reply'])->name('me.reviews.reply');

            Route::get('/me/offers', [OwnerOfferController::class, 'index'])->name('me.offers.index');
            Route::post('/me/offers', [OwnerOfferController::class, 'store'])->middleware('throttle:submissions')->name('me.offers.store');
            Route::get('/me/offers/{offer}', [OwnerOfferController::class, 'show'])->name('me.offers.show');
            Route::match(['put', 'patch'], '/me/offers/{offer}', [OwnerOfferController::class, 'update'])->name('me.offers.update');
            Route::delete('/me/offers/{offer}', [OwnerOfferController::class, 'destroy'])->name('me.offers.destroy');
        });
    });
});
