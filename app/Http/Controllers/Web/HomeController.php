<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Banner;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Offer;
use App\Support\Directory;
use App\Support\NavigationData;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(NavigationData $nav): View
    {
        $categoryCounts = Directory::categoryCounts();
        $categories = $nav->rootCategories()->where('is_featured', true)->values();

        $featured = Business::query()->published()->featured()->with(Directory::cardRelations())
            ->orderByDesc('is_verified')->orderByDesc('published_at')->limit(8)->get();

        $latest = Business::query()->published()->with(Directory::cardRelations())
            ->latest('published_at')->limit(8)->get();

        $popular = Business::query()->published()->with(Directory::cardRelations())
            ->orderByDesc('is_verified')->orderByRaw('(views_count + favorites_count * 5) DESC')->orderByRaw('rating_avg DESC NULLS LAST')
            ->limit(4)->get();

        $offers = setting('home.show_offers', true)
            ? Offer::query()->live()->with('business:id,name_ar,name_en,slug')->orderByDesc('is_featured')->orderBy('ends_at')->limit(3)->get()
            : collect();

        $locationCounts = Directory::municipalityCounts();
        $municipalities = $nav->municipalities()->where('is_featured', true)->values();
        $featuredAreas = Cache::remember('home.featured_areas', now()->addMinutes(10), fn () => Area::query()->active()
            ->where('is_featured', true)->with('municipality:id,name_ar,name_en,slug')->withCount(['businesses' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->limit(4)->get());

        $stats = Cache::remember('home.stats', now()->addMinutes(10), fn () => [
            'businesses' => Business::query()->published()->count(),
            'categories' => Category::query()->active()->count(),
            'areas' => Area::query()->active()->count(),
        ]);

        $banners = Banner::query()->live('home_promo')->limit(2)->get();

        seo()->title(null)
            ->description(setting_t('seo.default_description'))
            ->jsonLd(Directory::organizationSchema())
            ->jsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => __('brand.name'),
                'url' => route('home'),
                'inLanguage' => app()->getLocale(),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('search').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ]);

        return view('web.home', [
            'categories' => $categories,
            'allCategories' => $nav->rootCategories(),
            'categoryCounts' => $categoryCounts,
            'featured' => $featured,
            'latest' => $latest,
            'popular' => $popular,
            'offers' => $offers,
            'municipalities' => $municipalities,
            'allMunicipalities' => $nav->municipalities(),
            'featuredAreas' => $featuredAreas,
            'locationCounts' => $locationCounts,
            'stats' => $stats,
            'banners' => $banners,
            'favoriteIds' => Directory::favoriteIds(),
            'heroImage' => media_url(setting('home.hero_image_path')) ?? asset('images/doha-skyline.svg'),
        ]);
    }
}
