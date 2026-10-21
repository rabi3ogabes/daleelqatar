@props([
    'results', 'criteria', 'action',
    'categories' => collect(), 'municipalities' => collect(),
    'showCategory' => true, 'showMunicipality' => true, 'fixedMunicipality' => null,
    'hasRatings' => false, 'layout' => 'grid', 'favoriteIds' => [],
])
{{-- Directory results with filters (search, category pages, location pages). URLs stay shareable. --}}
@php
    // The page itself fixes the category / municipality, so they are not repeated in the query string.
    $query = \Illuminate\Support\Arr::except($criteria->toQuery(), array_merge($showCategory ? [] : ['category'], $showMunicipality ? [] : ['municipality']));
    $areas = $fixedMunicipality ? $fixedMunicipality->areas : ($criteria->municipality?->areas ?? collect());
    $without = function (string ...$keys) use ($query, $action) {
        $q = \Illuminate\Support\Arr::except($query, array_merge($keys, ['page']));
        return $action.($q ? '?'.http_build_query($q) : '');
    };
    $chips = array_filter([
        $criteria->query ? ['label' => '“'.$criteria->query.'”', 'url' => $without('q')] : null,
        $showCategory && $criteria->category ? ['label' => $criteria->category->t('name'), 'url' => $without('category')] : null,
        $showMunicipality && $criteria->municipality ? ['label' => $criteria->municipality->t('name'), 'url' => $without('municipality', 'area')] : null,
        $criteria->area ? ['label' => $criteria->area->t('name'), 'url' => $without('area')] : null,
        $criteria->verifiedOnly ? ['label' => __('search.verified_only'), 'url' => $without('verified')] : null,
        $criteria->openNow ? ['label' => __('search.open_now'), 'url' => $without('open_now')] : null,
        $criteria->minRating ? ['label' => __('search.rating_and_up', ['rating' => $criteria->minRating]), 'url' => $without('rating')] : null,
    ]);
    $viewUrl = fn ($v) => $action.'?'.http_build_query(array_merge(\Illuminate\Support\Arr::except($query, ['page']), ['view' => $v]));
@endphp
<div class="grid gap-8 lg:grid-cols-[17rem_1fr]" x-data="{ filters: false }">
    {{-- Filters --}}
    <aside>
        <button type="button" class="btn btn-outline w-full lg:hidden" @click="filters = !filters" :aria-expanded="filters.toString()" aria-controls="filters-panel">
            <x-icon name="sliders-horizontal" class="size-4" />{{ __('search.filters_title') }}
            @if($chips)<span class="rounded-full bg-navy-900 px-2 text-xs text-white">{{ count($chips) }}</span>@endif
        </button>
        <form id="filters-panel" method="GET" action="{{ $action }}" class="card mt-3 hidden space-y-5 p-5 lg:sticky lg:top-24 lg:mt-0 lg:block" :class="filters && '!block'">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-icon name="sliders-horizontal" class="size-4 text-gold-600" />{{ __('search.filters_title') }}</h2>
            <x-form.input name="q" :label="__('search.keyword')" :value="$criteria->query" type="search" icon="search" maxlength="120" />
            @if($showCategory)
                <x-form.select name="category" :label="__('search.category')" :placeholder="__('search.all_categories')" :value="$criteria->category?->slug"
                    :options="$categories->mapWithKeys(fn ($c) => [$c->t('name') => [$c->slug => $c->t('name').' — '.__('common.all')] + $c->children->mapWithKeys(fn ($s) => [$s->slug => $s->t('name')])->all()])->all()" />
            @endif
            @if($showMunicipality)
                <x-form.select name="municipality" :label="__('search.municipality')" :placeholder="__('search.all_municipalities')" :value="$criteria->municipality?->slug"
                    :options="$municipalities->mapWithKeys(fn ($m) => [$m->slug => $m->t('name')])->all()" onchange="this.form.area && (this.form.area.value = '')" />
            @endif
            @if($areas->isNotEmpty())
                <x-form.select name="area" :label="__('search.area')" :placeholder="__('search.all_areas')" :value="$criteria->area?->slug" :options="$areas->mapWithKeys(fn ($a) => [$a->slug => $a->t('name')])->all()" />
            @endif
            @if($hasRatings)
                <x-form.select name="rating" :label="__('search.min_rating')" :placeholder="__('search.any_rating')" :value="$criteria->minRating"
                    :options="collect([4, 3, 2])->mapWithKeys(fn ($r) => [$r => __('search.rating_and_up', ['rating' => $r])])->all()" />
            @endif
            <div class="space-y-3 border-t border-navy-50 pt-4">
                <x-form.checkbox name="verified" :label="__('search.verified_only')" :checked="$criteria->verifiedOnly" />
                <x-form.checkbox name="open_now" :label="__('search.open_now')" :checked="$criteria->openNow" />
            </div>
            @if($criteria->sort !== 'relevance')<input type="hidden" name="sort" value="{{ $criteria->sort }}">@endif
            @if($layout === 'list')<input type="hidden" name="view" value="list">@endif
            <div class="flex gap-2 pt-1">
                <button type="submit" class="btn btn-primary flex-1" data-no-busy>{{ __('search.show_results') }}</button>
                @if($chips)<a href="{{ $action }}" class="btn btn-ghost">{{ __('common.reset') }}</a>@endif
            </div>
        </form>
    </aside>

    {{-- Results --}}
    <div class="min-w-0">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-strong" role="status">
                <span class="font-semibold text-navy-900">{{ trans_choice('search.results_count', $results->total(), ['count' => format_number($results->total())]) }}</span>
            </p>
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ $action }}" class="flex items-center gap-2">
                    @foreach(\Illuminate\Support\Arr::except($query, ['sort']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    @if($layout === 'list')<input type="hidden" name="view" value="list">@endif
                    <label for="sort" class="hidden text-sm text-muted-strong sm:block">{{ __('common.sort_by') }}</label>
                    <select id="sort" name="sort" class="select !w-auto !py-2 text-sm" onchange="this.form.requestSubmit()">
                        @foreach(['relevance', 'newest', 'popular', 'name'] as $sort)
                            <option value="{{ $sort }}" @selected($criteria->sort === $sort)>{{ __('search.sort_'.$sort) }}</option>
                        @endforeach
                        @if($hasRatings)<option value="rating" @selected($criteria->sort === 'rating')>{{ __('search.sort_rating') }}</option>@endif
                    </select>
                    <noscript><button class="btn btn-outline btn-sm">{{ __('common.apply') }}</button></noscript>
                </form>
                <div class="flex rounded-xl border border-navy-100 bg-white p-1" role="group" aria-label="{{ __('search.view_grid') }} / {{ __('search.view_list') }}">
                    <a href="{{ $viewUrl('grid') }}" @class(['btn-icon size-8', 'bg-navy-900 text-white' => $layout === 'grid', 'text-muted hover:text-navy-900' => $layout !== 'grid']) aria-label="{{ __('search.view_grid') }}" @if($layout === 'grid') aria-current="true" @endif><x-icon name="layout-grid" class="size-4" /></a>
                    <a href="{{ $viewUrl('list') }}" @class(['btn-icon size-8', 'bg-navy-900 text-white' => $layout === 'list', 'text-muted hover:text-navy-900' => $layout !== 'list']) aria-label="{{ __('search.view_list') }}" @if($layout === 'list') aria-current="true" @endif><x-icon name="list" class="size-4" /></a>
                </div>
            </div>
        </div>

        @if($chips)
            <div class="mb-5 flex flex-wrap items-center gap-2" aria-label="{{ __('search.active_filters') }}">
                @foreach($chips as $chip)
                    <a href="{{ $chip['url'] }}" class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 py-1 ps-3 pe-2 text-xs font-medium text-white transition hover:bg-navy-700">
                        {{ $chip['label'] }}<x-icon name="x" class="size-3.5 text-gold-300" /><span class="sr-only">{{ __('common.remove') }}</span>
                    </a>
                @endforeach
                <a href="{{ $action }}" class="text-xs font-medium text-gold-700 hover:underline">{{ __('common.clear_filters') }}</a>
            </div>
        @endif

        @if($results->isNotEmpty())
            <div @class(['grid gap-5 sm:grid-cols-2 xl:grid-cols-3' => $layout === 'grid', 'grid gap-4' => $layout === 'list'])>
                @foreach($results as $business)
                    <x-business-card :business="$business" :layout="$layout" :favorited="in_array($business->id, $favoriteIds, true)" :eager="$loop->index < 3" />
                @endforeach
            </div>
            {{ $results->onEachSide(1)->links() }}
        @else
            <x-empty-state icon="text-search" :title="__('search.empty_title')" :text="__('search.empty_text')">
                <div class="w-full text-sm text-muted-strong">
                    <p class="font-medium text-navy-900">{{ __('search.suggestions') }}</p>
                    <ul class="mt-2 space-y-1">
                        <li>{{ __('search.suggestion_1') }}</li><li>{{ __('search.suggestion_2') }}</li><li>{{ __('search.suggestion_3') }}</li>
                    </ul>
                </div>
                @if($chips)<a href="{{ $action }}" class="btn btn-outline">{{ __('common.clear_filters') }}</a>@endif
                <a href="{{ route('categories.index') }}" class="btn btn-primary">{{ __('search.browse_categories') }}</a>
            </x-empty-state>
        @endif
    </div>
</div>
