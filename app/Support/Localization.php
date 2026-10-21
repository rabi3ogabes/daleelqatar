<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * URL strategy: Arabic (default) is served without a prefix, English under /en.
 * Every localized route is registered twice, named "ar.<name>" and "en.<name>".
 * route('<name>') resolves to the active locale automatically (see AppServiceProvider).
 */
final class Localization
{
    public const LOCALES = ['ar', 'en'];

    public const DEFAULT = 'ar';

    public static function prefix(string $locale): string
    {
        return $locale === self::DEFAULT ? '' : $locale;
    }

    public static function baseRouteName(string $name): string
    {
        foreach (self::LOCALES as $l) {
            if (str_starts_with($name, $l.'.')) {
                return substr($name, strlen($l) + 1);
            }
        }

        return $name;
    }

    public static function route(string $locale, string $name, array $parameters = [], bool $absolute = true): string
    {
        return route($locale.'.'.$name, $parameters, $absolute);
    }

    /** Current URL in $locale, keeping route parameters and the query string (e.g. search filters). */
    public static function switchUrl(string $locale, ?string $fallbackRoute = null): string
    {
        $request = request();
        $route = $request->route();
        $query = $request->getQueryString();

        if ($route && $route->getName()) {
            $target = $locale.'.'.self::baseRouteName($route->getName());
            if (Route::has($target)) {
                $params = $route->parameters();
                foreach ($params as $key => $value) {
                    if (is_object($value) && method_exists($value, 'getRouteKey')) {
                        $params[$key] = $value->getRouteKey();
                    }
                }
                $url = route($target, $params);

                return $query ? $url.'?'.$query : $url;
            }
        }

        // Fallback: swap the path prefix
        $path = trim($request->path(), '/');
        $path = preg_replace('#^en(/|$)#', '', $path);
        $prefix = self::prefix($locale);
        $url = url(trim($prefix.'/'.$path, '/'));

        if ($fallbackRoute && ! $route) {
            $url = route($locale.'.'.$fallbackRoute);
        }

        return $query ? $url.'?'.$query : $url;
    }

    public static function isLocalizedPath(string $path): bool
    {
        return Str::startsWith(trim($path, '/'), 'en');
    }
}
