<?php

declare(strict_types=1);

use App\Services\SettingsService;
use App\Support\Localization;
use Illuminate\Support\Facades\Storage;

if (! function_exists('media_url')) {
    /** Public URL of a file stored on the configured media disk (Supabase Storage in production). */
    function media_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, 'brand/') || str_starts_with($path, 'images/')) {
            return asset($path);
        }

        return Storage::disk(config('filesystems.media_disk'))->url($path);
    }
}

if (! function_exists('setting')) {
    /** Read an administrator-editable site setting (cached). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('setting_t')) {
    /** Read a bilingual setting stored as {"ar": "...", "en": "..."} for the current locale. */
    function setting_t(string $key, ?string $default = null): ?string
    {
        $value = setting($key);
        if (is_array($value)) {
            $locale = app()->getLocale();

            return ($value[$locale] ?? null) ?: ($value[$locale === 'ar' ? 'en' : 'ar'] ?? null) ?: $default;
        }

        return is_string($value) && $value !== '' ? $value : $default;
    }
}

if (! function_exists('locale_is_rtl')) {
    function locale_is_rtl(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'ar';
    }
}

if (! function_exists('localized_url')) {
    /** The current page in another language, preserving route parameters and query string. */
    function localized_url(string $locale, ?string $fallbackRoute = null): string
    {
        return Localization::switchUrl($locale, $fallbackRoute);
    }
}

if (! function_exists('route_is')) {
    /** Locale-agnostic version of request()->routeIs(). */
    function route_is(string ...$patterns): bool
    {
        $name = request()->route()?->getName();
        if (! $name) {
            return false;
        }
        $base = Localization::baseRouteName($name);

        foreach ($patterns as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $base)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('format_time')) {
    /** 24:00:00 → "12:00 AM" style label in the current locale. */
    function format_time(string $time): string
    {
        if (str_starts_with($time, '24:00')) {
            $time = '00:00';
        }
        $dt = \Carbon\Carbon::createFromFormat('H:i', substr($time, 0, 5));

        return $dt->locale(app()->getLocale())->isoFormat('h:mm A');
    }
}

if (! function_exists('format_number')) {
    function format_number(int|float|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals);
    }
}

if (! function_exists('format_date')) {
    function format_date(?\DateTimeInterface $date, string $format = 'D MMM YYYY'): string
    {
        return $date ? \Carbon\Carbon::instance($date)->locale(app()->getLocale())->isoFormat($format) : '—';
    }
}

if (! function_exists('ip_hash')) {
    /** Salted, non-reversible fingerprint of the client IP (analytics & abuse prevention without storing IPs). */
    function ip_hash(?string $ip = null): ?string
    {
        $ip ??= request()->ip();

        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}

if (! function_exists('safe_external_url')) {
    /** Returns the URL only when it is an absolute http(s) URL, otherwise null. */
    function safe_external_url(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
