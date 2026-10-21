<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Offer;
use App\Models\Page;
use App\Support\Directory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    /**
     * XML sitemap with Arabic/English alternates. Only published, non-demo
     * listings and non-empty category/location pages are included.
     */
    public function sitemap(): Response
    {
        abort_unless(setting('seo.sitemap_enabled', true), 404);

        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $entries = [];
            $add = function (string $name, array $params = [], ?\DateTimeInterface $modified = null, string $priority = '0.6') use (&$entries) {
                $entries[] = [
                    'ar' => route('ar.'.$name, $params),
                    'en' => route('en.'.$name, $params),
                    'lastmod' => $modified?->format('Y-m-d'),
                    'priority' => $priority,
                ];
            };

            $add('home', [], now(), '1.0');
            foreach (['categories.index', 'locations.index', 'offers.index', 'about', 'faq', 'contact', 'add-business', 'businesses.latest'] as $static) {
                $add($static, [], null, '0.5');
            }

            $categoryCounts = Directory::categoryCounts();
            Category::query()->active()->get(['id', 'slug', 'parent_id', 'updated_at'])->each(function (Category $c) use ($add, $categoryCounts) {
                $count = $c->parent_id === null
                    ? ($categoryCounts[$c->id] ?? 0)
                    : Business::query()->published()->where('category_id', $c->id)->count();
                if ($count > 0) {
                    $add('categories.show', ['category' => $c->slug], $c->updated_at, '0.7');
                }
            });

            $municipalityCounts = Directory::municipalityCounts();
            Municipality::query()->active()->get(['id', 'slug', 'updated_at'])->each(function (Municipality $m) use ($add, $municipalityCounts) {
                if (($municipalityCounts[$m->id] ?? 0) > 0) {
                    $add('locations.show', ['municipality' => $m->slug], $m->updated_at, '0.6');
                }
            });

            Business::query()->published()->where('is_demo', false)->select(['id', 'slug', 'updated_at'])
                ->orderBy('id')->chunk(1000, function ($chunk) use ($add) {
                    foreach ($chunk as $b) {
                        $add('business.show', ['business' => $b->slug], $b->updated_at, '0.8');
                    }
                });

            Offer::query()->live()->select(['offers.id', 'offers.slug', 'offers.updated_at', 'offers.business_id'])->get()
                ->each(fn ($o) => $add('offers.show', ['offer' => $o->slug], $o->updated_at, '0.4'));

            Page::query()->where('is_published', true)->whereIn('slug', ['privacy', 'terms'])->get()
                ->each(fn ($p) => $add($p->slug, [], $p->updated_at, '0.2'));

            return view('seo.sitemap', ['entries' => $entries])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
            'Disallow: /*?*sort=',
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
