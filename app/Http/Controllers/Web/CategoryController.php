<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Contracts\BusinessSearchEngine;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Search\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(NavigationData $nav): View
    {
        seo()->title(__('categories.title'))->description(__('categories.subtitle'));

        return view('web.categories.index', [
            'categories' => $nav->rootCategories(),
            'counts' => Directory::categoryCounts(),
        ]);
    }

    public function show(Request $request, Category $category, BusinessSearchEngine $engine, NavigationData $nav): View
    {
        abort_unless($category->is_active, 404);
        $category->load(['parent', 'children' => fn ($q) => $q->active()]);

        $request->query->set('category', $category->slug);
        $criteria = SearchCriteria::fromRequest($request);
        $results = $engine->search($criteria);

        $name = $category->t('name');
        // Empty category pages are kept out of search indexes (no thin pages).
        seo()->title($category->t('meta_title') ?: $name)
            ->description($category->t('meta_description') ?: __('categories.meta_description', ['category' => $name]))
            ->image($category->imageUrl())
            ->noindex($results->total() === 0 || $request->hasAny(['q', 'municipality', 'area', 'verified', 'rating', 'open_now', 'sort']));

        return view('web.categories.show', [
            'category' => $category,
            'criteria' => $criteria,
            'results' => $results,
            'municipalities' => $nav->municipalities(),
            'favoriteIds' => Directory::favoriteIds(),
        ]);
    }
}
