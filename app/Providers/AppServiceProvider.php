<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BusinessSearchEngine;
use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use App\Models\User;
use App\Policies\BusinessPolicy;
use App\Policies\OfferPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use App\Services\SettingsService;
use App\Support\Localization;
use App\Support\Permissions;
use App\Support\Search\PostgresBusinessSearch;
use App\Support\Storage\SupabaseStorageAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->bind(BusinessSearchEngine::class, PostgresBusinessSearch::class);
    }

    public function boot(): void
    {
        $this->configureStorage();
        $this->configureLocalizedRoutes();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'business' => Business::class,
            'review' => Review::class,
            'offer' => Offer::class,
            'category' => \App\Models\Category::class,
            'municipality' => \App\Models\Municipality::class,
            'area' => \App\Models\Area::class,
            'claim' => \App\Models\BusinessClaimRequest::class,
            'verification' => \App\Models\BusinessVerificationRequest::class,
            'page' => \App\Models\Page::class,
            'faq' => \App\Models\Faq::class,
            'banner' => \App\Models\Banner::class,
            'setting' => \App\Models\SiteSetting::class,
            'listing_report' => \App\Models\ListingReport::class,
            'review_report' => \App\Models\ReviewReport::class,
            'contact_message' => \App\Models\ContactMessage::class,
            'role' => \Spatie\Permission\Models\Role::class,
        ]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configureStorage(): void
    {
        Storage::extend('supabase', function ($app, array $config) {
            $adapter = new SupabaseStorageAdapter(
                (string) $config['url'],
                (string) $config['key'],
                (string) $config['bucket'],
                (bool) ($config['public'] ?? true),
                (int) ($config['signed_url_ttl'] ?? 600),
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }

    /**
     * Localized routes are registered as "ar.<name>" and "en.<name>". A bare
     * route('<name>') resolves to the active language automatically, so
     * controllers, views, redirects and e-mails never need to know the locale.
     */
    private function configureLocalizedRoutes(): void
    {
        URL::resolveMissingNamedRoutesUsing(function (string $name, $parameters, bool $absolute) {
            $locale = in_array(app()->getLocale(), Localization::LOCALES, true) ? app()->getLocale() : Localization::DEFAULT;
            $localized = $locale.'.'.$name;

            return Route::has($localized) ? app('url')->route($localized, $parameters, $absolute) : null;
        });
    }

    private function configureAuthorization(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Super administrators may do everything — except escalate/suspend themselves via UserPolicy.
        Gate::before(function (User $user, string $ability) {
            if (in_array($ability, ['assignRoles', 'suspend'], true)) {
                return null;
            }

            return $user->hasRole(Permissions::SUPER_ADMIN) ? true : null;
        });
    }

    private function configureRateLimiting(): void
    {
        $key = fn (Request $r) => $r->user()?->id ? 'u'.$r->user()->id : 'ip'.$r->ip();

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
        RateLimiter::for('password', fn (Request $r) => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('submissions', fn (Request $r) => Limit::perHour(15)->by($key($r)));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(120)->by($key($r)));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(90)->by($r->ip()));
        RateLimiter::for('interactions', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($key($r)));
        RateLimiter::for('api-auth', fn (Request $r) => [
            Limit::perMinute(6)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
    }
}
