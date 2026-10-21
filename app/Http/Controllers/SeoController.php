<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Integrations;
use App\Support\Sitemap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /** Sitemap index submitted to Google Search Console and Bing Webmaster Tools. */
    public function sitemap(): Response
    {
        abort_unless(setting('seo.sitemap_enabled', true), 404);

        return response(Sitemap::index(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemapPart(string $part): Response
    {
        abort_unless(setting('seo.sitemap_enabled', true) && Sitemap::isPart($part), 404);

        return response(Sitemap::part($part), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Authorised digital sellers (IAB ads.txt) for Google AdSense. */
    public function adsTxt(): Response
    {
        $client = Integrations::adsenseClient();
        abort_if($client === null, 404);

        return response('google.com, '.substr($client, 3).', DIRECT, f08c47fec0942fa0'."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** IndexNow ownership key (referenced as keyLocation when URLs are submitted). */
    public function indexNowKey(): Response
    {
        return response(Integrations::indexNowKey(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Fresh CSRF token for forms on CDN-cached pages (which are served without a session). */
    public function csrfToken(Request $request): JsonResponse
    {
        abort_unless($request->ajax(), 404);

        return response()->json(['token' => csrf_token()], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function robots(): Response
    {
        $indexable = app()->isProduction() && setting('seo.indexing_enabled', true);
        $lines = $indexable ? [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin', 'Disallow: /en/admin',
            'Disallow: /account', 'Disallow: /en/account',
            'Disallow: /login', 'Disallow: /en/login',
            'Disallow: /register', 'Disallow: /en/register',
            'Disallow: /search', 'Disallow: /en/search',
            'Disallow: /api/',
            'Disallow: /cron/',
            'Disallow: /*?*sort=',
            'Disallow: /business/*/reveal/', 'Disallow: /en/business/*/reveal/',
            'Disallow: /map/data.json', 'Disallow: /en/map/data.json',
            'Disallow: /business/*/google.json', 'Disallow: /en/business/*/google.json',
            'Disallow: /csrf-token',
            '',
            'Sitemap: '.route('sitemap'),
        ] : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'دليل قطر — Daleel Qatar',
            'short_name' => 'دليل قطر',
            'description' => __('brand.subtitle', [], 'ar').' '.__('brand.subtitle', [], 'en'),
            'start_url' => '/',
            'display' => 'standalone',
            'dir' => 'rtl',
            'lang' => 'ar',
            'background_color' => '#F7F4ED',
            'theme_color' => '#101C32',
            'icons' => [
                ['src' => asset('brand/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('brand/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('brand/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
