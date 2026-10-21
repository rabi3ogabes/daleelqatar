<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sets the application language from the route group (ar = no prefix, en = /en). */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        // Without a parameter (global middleware) the locale is detected from the path,
        // so error pages for unmatched URLs are rendered in the right language too.
        // API requests negotiate their language via Accept-Language (ApiLocale).
        if ($locale === null && $request->is('api/*')) {
            return $next($request);
        }
        $locale ??= preg_match('#^en(/|$)#', $request->path()) ? 'en' : 'ar';
        $locale = in_array($locale, config('app.supported_locales', ['ar', 'en']), true) ? $locale : 'ar';
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
