<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the CDN (Vercel Edge) serve public pages to first-time visitors and
 * search-engine crawlers without running PHP or touching the database.
 *
 * Only responses rendered for requests that carry no cookies at all are
 * cached, so they can never contain anything personal: no session, no user,
 * no flash message. Their Set-Cookie headers are dropped and "Vary: Cookie"
 * keeps returning visitors and signed-in users on fresh, uncached pages.
 * Forms and beacons on a cached page fetch a fresh CSRF token first (app.js).
 */
class CacheGuestPages
{
    /** Public HTML pages (route name without locale prefix) => CDN lifetime in seconds. */
    private const PAGES = [
        'home' => 300,
        'categories.index' => 900,
        'categories.show' => 600,
        'locations.index' => 900,
        'locations.show' => 600,
        'map' => 900,
        'businesses.featured' => 600,
        'businesses.latest' => 300,
        'business.show' => 600,
        'offers.index' => 300,
        'offers.show' => 600,
        'directory.az' => 1800,
        'directory.az.letter' => 1800,
        'about' => 3600,
        'faq' => 3600,
        'privacy' => 3600,
        'terms' => 3600,
        'add-business' => 3600,
    ];

    /** Identical for every visitor: cached whatever cookies the request carries. */
    private const SHARED = [
        'map.data' => 3600,
        'business.google' => 3600,
        'sitemap' => 3600,
        'sitemap.part' => 3600,
        'robots' => 3600,
        'manifest' => 86400,
        'ads.txt' => 3600,
        'indexnow.key' => 86400,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('cache.edge.enabled') || ! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $name = Localization::baseRouteName((string) $request->route()?->getName());

        if (isset(self::SHARED[$name])) {
            return $this->cache($response, self::SHARED[$name], false);
        }

        if (isset(self::PAGES[$name]) && $request->cookies->count() === 0 && auth()->guest()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $this->markHtml($response);

            return $this->cache($response, (int) round(self::PAGES[$name] * (float) config('cache.edge.ttl_factor', 1)), true);
        }

        return $response;
    }

    private function cache(Response $response, int $ttl, bool $varyOnCookie): Response
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        if (! str_contains((string) $response->headers->get('Cache-Control'), 's-maxage')) {
            $response->headers->set('Cache-Control', "public, max-age=0, s-maxage={$ttl}, stale-while-revalidate=86400");
        }
        if ($varyOnCookie) {
            $response->setVary(array_values(array_unique([...$response->getVary(), 'Cookie'])));
        }

        return $response;
    }

    /** Tells app.js the page may come from the CDN, so it must fetch a CSRF token before posting. */
    private function markHtml(Response $response): void
    {
        $content = $response->getContent();
        if (is_string($content) && ($pos = strpos($content, '<html ')) !== false) {
            $response->setContent(substr_replace($content, '<html data-edge="1" ', $pos, 6));
        }
    }
}
