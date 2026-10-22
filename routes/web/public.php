<?php

use App\Http\Controllers\Web\AzController;
use App\Http\Controllers\Web\BusinessController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\ClaimController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\FavoriteController;
use App\Http\Controllers\Web\GuideController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\OfferController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\QuoteController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ReviewController;
use App\Http\Controllers\Web\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('throttle:search')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
});

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
Route::get('/locations/{municipality:slug}', [LocationController::class, 'show'])->name('locations.show');

Route::get('/map', [MapController::class, 'index'])->name('map');
Route::get('/map/data.json', [MapController::class, 'data'])->name('map.data');

Route::get('/directory', [AzController::class, 'index'])->name('directory.az');
Route::get('/directory/{letter}', [AzController::class, 'letter'])->name('directory.az.letter');

Route::get('/featured', [BusinessController::class, 'featured'])->name('businesses.featured');
Route::get('/latest', [BusinessController::class, 'latest'])->name('businesses.latest');
Route::get('/business/{business:slug}', [BusinessController::class, 'show'])->name('business.show');
Route::get('/business/{business:slug}/reveal/{type}', [BusinessController::class, 'reveal'])->whereIn('type', ['phone', 'whatsapp', 'email'])->middleware('throttle:reveal')->name('business.reveal');
Route::get('/business/{business:slug}/google.json', [BusinessController::class, 'google'])->middleware('throttle:60,1')->name('business.google');
Route::post('/business/{business:slug}/viewed', [BusinessController::class, 'viewed'])->middleware('throttle:interactions')->name('business.viewed');
Route::post('/business/{business:slug}/track', [BusinessController::class, 'track'])->middleware('throttle:interactions')->name('business.track');
Route::post('/business/{business:slug}/report', [ReportController::class, 'store'])->middleware('throttle:submissions')->name('business.report');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/business/{business:slug}/favorite', [FavoriteController::class, 'toggle'])->middleware('throttle:interactions')->name('business.favorite');
    Route::post('/business/{business:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:submissions')->name('business.reviews.store');
    Route::post('/reviews/{review:uuid}/report', [ReviewController::class, 'report'])->middleware('throttle:submissions')->name('reviews.report');
    Route::get('/business/{business:slug}/claim', [ClaimController::class, 'create'])->name('business.claim');
    Route::post('/business/{business:slug}/claim', [ClaimController::class, 'store'])->middleware(['throttle:submissions'])->name('business.claim.store');
});

// Quote requests: anyone can open the form; sending needs a verified account (replies arrive there).
Route::get('/quotes/new', [QuoteController::class, 'create'])->name('quotes.create');
Route::get('/quotes/preview', [QuoteController::class, 'preview'])->middleware('throttle:60,1')->name('quotes.preview');
Route::post('/quotes', [QuoteController::class, 'store'])->middleware(['auth', 'verified', 'throttle:submissions'])->name('quotes.store');

Route::get('/guides', [GuideController::class, 'index'])->name('guides.index');
Route::get('/guides/{guide:slug}', [GuideController::class, 'show'])->name('guides.show');

Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
Route::get('/offers/{offer:slug}', [OfferController::class, 'show'])->name('offers.show');

Route::get('/add-your-business', [PageController::class, 'addBusiness'])->name('add-business');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/cookies', [PageController::class, 'cookies'])->name('cookies');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('disclaimer');
Route::get('/editorial-policy', [PageController::class, 'editorial'])->name('editorial');
Route::get('/advertise', [PageController::class, 'advertise'])->name('advertise');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:submissions')->name('contact.store');
