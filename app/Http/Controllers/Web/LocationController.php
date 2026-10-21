<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Contracts\BusinessSearchEngine;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Search\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(NavigationData $nav): View
    {
        seo()->title(__('locations.title'))->description(__('locations.subtitle'));

        return view('web.locations.index', [
            'municipalities' => $nav->municipalities(),
            'counts' => Directory::municipalityCounts(),
        ]);
    }

    public function show(Request $request, Municipality $municipality, BusinessSearchEngine $engine, NavigationData $nav): View
    {
        abort_unless($municipality->is_active, 404);
        $municipality->load(['areas' => fn ($q) => $q->active()]);

        $request->query->set('municipality', $municipality->slug);
        $criteria = SearchCriteria::fromRequest($request);
        $results = $engine->search($criteria);

        $name = $municipality->t('name');
        seo()->title(__('locations.businesses_in', ['location' => $name]))
            ->description($municipality->t('description') ?: __('locations.meta_description', ['location' => $name]))
            ->noindex($results->total() === 0 || $request->hasAny(['q', 'category', 'area', 'verified', 'rating', 'open_now', 'sort']));

        return view('web.locations.show', [
            'municipality' => $municipality,
            'criteria' => $criteria,
            'results' => $results,
            'categories' => $nav->rootCategories(),
            'favoriteIds' => Directory::favoriteIds(),
        ]);
    }
}
