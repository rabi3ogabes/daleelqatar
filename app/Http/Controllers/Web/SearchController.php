<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Contracts\BusinessSearchEngine;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AnalyticsRecorder;
use App\Support\Directory;
use App\Support\NavigationData;
use App\Support\Search\ArabicNormalizer;
use App\Support\Search\SearchCriteria;
use App\Support\Search\SmartQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, BusinessSearchEngine $engine, AnalyticsRecorder $analytics, NavigationData $nav, SmartQuery $smart): View|RedirectResponse
    {
        $criteria = SearchCriteria::fromRequest($request);

        // Smart search: a plain sentence becomes the matching filters ("pharmacy open now in Al Wakrah").
        if ($criteria->query !== null && SmartQuery::enabled() && ! $request->boolean('exact') && ! $request->filled('understood')
            && ! $request->filled('category') && ! $request->filled('municipality') && ! $request->filled('area') && ! $request->boolean('open_now') && ! $request->boolean('verified')) {
            $meaning = $smart->interpret($criteria->query);
            if ($meaning?->understood()) {
                return redirect()->route('search', $meaning->toQuery($criteria->query) + array_filter(['view' => $request->query('view')]));
            }
        }

        $results = $engine->search($criteria);
        $understood = $request->filled('understood') ? mb_substr(trim((string) $request->query('understood')), 0, 120) : null;
        $broadened = null;
        // Words left over from a smart search ("kids") narrowed it to nothing: show the understood filters alone.
        if ($understood !== null && $results->total() === 0 && $criteria->query !== null && $criteria->hasFilters()) {
            $broadened = $criteria->query;
            $criteria = $criteria->withoutQuery();
            $results = $engine->search($criteria);
        }
        // Nothing with the understood filters: the words exactly as typed, if they find something.
        if ($understood !== null && $results->total() === 0) {
            $exact = new SearchCriteria(query: $understood, perPage: $criteria->perPage, page: $criteria->page);
            $exactResults = $engine->search($exact);
            if ($exactResults->total() > 0) {
                [$criteria, $results, $understood, $broadened] = [$exact, $exactResults, null, null];
            }
        }
        $analytics->recordSearch($criteria, $results->total());

        $title = $criteria->query ? __('search.results_for', ['query' => $criteria->query]) : __('search.title');
        // Result pages are useful to people but thin for search engines: noindex, follow.
        seo()->title($title)->description(__('search.meta_description'))->noindex()->canonical(route('search'));

        return view('web.search', [
            'criteria' => $criteria,
            'results' => $results,
            'categories' => $nav->rootCategories(),
            'municipalities' => $nav->municipalities(),
            'hasRatings' => DB::table('businesses')->whereNotNull('rating_avg')->exists(),
            'layout' => $request->query('view') === 'list' ? 'list' : 'grid',
            'favoriteIds' => Directory::favoriteIds(),
            'understood' => $understood,
            'broadened' => $broadened,
        ]);
    }

    /** Lightweight autocomplete for the search box. */
    public function suggest(Request $request, BusinessSearchEngine $engine): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        if (mb_strlen($q) < 2) {
            return response()->json(['categories' => [], 'businesses' => []]);
        }

        $like = '%'.addcslashes(ArabicNormalizer::normalize($q), '%_\\').'%';
        $categories = Category::query()->active()
            ->where(fn (Builder $w) => $w->whereRaw(ArabicNormalizer::sql('name_ar').' ILIKE ?', [$like])->orWhereRaw(ArabicNormalizer::sql('name_en').' ILIKE ?', [$like]))
            ->ordered()->limit(4)->get()
            ->map(fn (Category $c) => ['type' => 'category', 'name' => $c->t('name'), 'icon' => $c->icon, 'url' => route('categories.show', $c)]);

        $businesses = $engine->search(new SearchCriteria(query: $q, perPage: 6))->getCollection()
            ->map(fn ($b) => [
                'type' => 'business',
                'name' => $b->t('name'),
                'meta' => trim(($b->category?->t('name') ?? '').' · '.($b->locationLabel() ?? ''), ' ·'),
                'verified' => $b->is_verified,
                'url' => route('business.show', $b),
            ]);

        return response()->json(['categories' => $categories->values(), 'businesses' => $businesses->values()])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
