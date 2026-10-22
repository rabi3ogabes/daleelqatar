<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Support\Directory;
use App\Support\Guides\GuideBuilder;
use App\Support\Insights\Impressions;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Editorial guides: "Pharmacies open 24 hours in Doha", built from real, complete listings. */
class GuideController extends Controller
{
    public function index(): View
    {
        $guides = Guide::query()->published()->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en'])
            ->latest('published_at')->get();

        seo()->title(__('guides.index_title'))->description(__('guides.index_description'))->noindex($guides->isEmpty());

        return view('web.guides.index', ['guides' => $guides]);
    }

    public function show(Guide $guide, GuideBuilder $builder): View
    {
        abort_unless($guide->isPublished() || auth()->user()?->can('guides.manage'), 404);

        $guide->load(['category', 'municipality', 'area']);
        $listings = $builder->listings($guide);
        $title = $guide->t('title');
        $url = route('guides.show', $guide);

        seo()->title($title)
            ->description($guide->t('meta_description') ?: Str::limit(strip_tags((string) $guide->t('intro')), 160))
            ->canonical($url)
            ->noindex(! $builder->indexable($guide, $listings->count()))
            ->jsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $title,
                'url' => $url,
                'numberOfItems' => $listings->count(),
                'itemListElement' => $listings->values()->map(fn ($b, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => route('business.show', $b),
                    'name' => $b->t('name'),
                ])->all(),
            ]);

        return view('web.guides.show', [
            'guide' => $guide,
            'listings' => $listings,
            'impressions' => Impressions::payload($listings->pluck('id')),
            'favoriteIds' => Directory::favoriteIds(),
            'related' => Guide::query()->published()->whereKeyNot($guide->id)
                ->where(fn ($q) => $q->where('category_id', $guide->category_id)->orWhere('municipality_id', $guide->municipality_id))
                ->latest('published_at')->limit(4)->get(),
        ]);
    }
}
