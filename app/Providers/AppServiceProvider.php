<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BusinessSearchEngine;
use App\Models\Area;
use App\Models\Banner;
use App\Models\Business;
use App\Models\BusinessClaimRequest;
use App\Models\BusinessVerificationRequest;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\ListingReport;
use App\Models\Municipality;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\SiteSetting;
use App\Models\User;
use App\Policies\BusinessPolicy;
use App\Policies\OfferPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use App\Services\SettingsService;
use App\Support\Enrichment\AiWriter;
use App\Support\Enrichment\ClaudeAiWriter;
use App\Support\Enrichment\NullAiWriter;
use App\Support\Localization;
use App\Support\Permissions;
use App\Support\Search\PostgresBusinessSearch;
use App\Support\SeoMeta;
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
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiWriter::class, fn () => trim((string) config('services.anthropic.key')) !== ''
            ? new ClaudeAiWriter
            : new NullAiWriter);

        $this->app->singleton(SettingsService::class);
        $this->app->scoped(SeoMeta::class);
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
            'category' => Category::class,
            'municipality' => Municipality::class,
            'area' => Area::class,
            'claim' => BusinessClaimRequest::class,
            'verification' => BusinessVerificationRequest::class,
            'page' => Page::class,
            'faq' => Faq::class,
            'banner' => Banner::class,
            'setting' => SiteSetting::class,
            'listing_report' => ListingReport::class,
            'review_report' => ReviewReport::class,
            'contact_message' => ContactMessage::class,
            'role' => Role::class,
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
        // Revealing hidden phone numbers / e-mails: generous for people, useless for harvesting.
        RateLimiter::for('reveal', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perDay(300)->by($r->ip())]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($key($r)));
        RateLimiter::for('api-auth', fn (Request $r) => [
            Limit::perMinute(6)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
    }
}
