<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Interactive map of Qatar showing every published business with an exact location. */
class MapController extends Controller
{
    public function index(): View
    {
        $counts = DB::table('businesses')
            ->join('categories', 'categories.id', '=', 'businesses.category_id')
            ->where('businesses.status', 'approved')->whereNotNull('businesses.published_at')->whereNull('businesses.deleted_at')
            ->whereNotNull('businesses.latitude')
            ->selectRaw('COALESCE(categories.parent_id, categories.id) AS root_id, COUNT(*) AS total')
            ->groupByRaw('COALESCE(categories.parent_id, categories.id)')
            ->pluck('total', 'root_id');

        $roots = Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'name_en', 'icon'])
            ->filter(fn ($c) => ($counts[$c->id] ?? 0) > 0)->values();

        seo()->title(__('directory.map.title'))->description(__('directory.map.seo_description'));

        return view('web.map', [
            'roots' => $roots,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
        ]);
    }

    /**
     * Compact point list: [id, lat, lng, name, slug, category_id, location].
     * Cached for an hour and served with CDN caching headers.
     */
    public function data(): JsonResponse
    {
        $locale = app()->getLocale();

        $payload = Cache::remember('map.points.'.$locale, now()->addHour(), function () use ($locale) {
            $points = Business::query()->published()
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->with(['area:id,name_ar,name_en', 'municipality:id,name_ar,name_en'])
                ->orderBy('id')
                ->get(['id', 'slug', 'name_ar', 'name_en', 'category_id', 'latitude', 'longitude', 'area_id', 'municipality_id'])
                ->map(fn (Business $b) => [
                    $b->id, round((float) $b->latitude, 6), round((float) $b->longitude, 6),
                    (string) $b->t('name', $locale), $b->slug, $b->category_id, $b->locationLabel($locale),
                ])->all();

            $categories = Category::query()->get(['id', 'parent_id', 'name_ar', 'name_en'])
                ->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->t('name', $locale), 'root' => $c->parent_id ?? $c->id]])->all();

            return ['points' => $points, 'categories' => $categories];
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=300, s-maxage=3600, stale-while-revalidate=86400')
            ->header('X-Robots-Tag', 'noindex');
    }
}
