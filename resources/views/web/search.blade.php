<x-layouts.app>
    <section class="relative overflow-hidden bg-navy-900">
        <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
        <div class="relative container-app py-8 sm:py-10">
            <x-breadcrumbs :items="[['label' => __('search.title')]]" dark />
            <h1 class="mt-4 text-2xl font-bold !text-white sm:text-3xl">
                {{ ($understood ?? $criteria->query) ? __('search.results_for', ['query' => $understood ?? $criteria->query]) : __('search.title') }}
            </h1>
            <div class="mt-6 max-w-4xl">
                <x-search-form :criteria="$criteria" :categories="$categories" :municipalities="$municipalities" variant="compact" />
            </div>
        </div>
    </section>
    <div class="container-app py-10">
        @if($understood)
            @php
                $parts = array_values(array_filter([
                    $criteria->category ? ['icon' => $criteria->category->icon ?: 'layers', 'label' => $criteria->category->t('name')] : null,
                    ($criteria->area ?? $criteria->municipality) ? ['icon' => 'map-pin', 'label' => ($criteria->area ?? $criteria->municipality)->t('name')] : null,
                    $criteria->openNow ? ['icon' => 'clock', 'label' => __('search.open_now')] : null,
                    $criteria->verifiedOnly ? ['icon' => 'badge-check', 'label' => __('business.verified')] : null,
                    $criteria->query ? ['icon' => 'search', 'label' => '“'.$criteria->query.'”'] : null,
                ]));
            @endphp
            <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-gold-200 bg-gradient-to-br from-gold-50 to-white p-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-900"><x-icon name="sparkles" class="size-4 text-gold-600" />{{ __('search.smart.understood') }}</span>
                    @foreach($parts as $part)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-navy-900 ring-1 ring-gold-200"><x-icon :name="$part['icon']" class="size-3.5 text-gold-700" />{{ $part['label'] }}</span>
                    @endforeach
                </div>
                <a href="{{ route('search', ['q' => $understood, 'exact' => 1]) }}" class="text-xs font-medium text-gold-800 hover:underline">{{ __('search.smart.exact', ['query' => $understood]) }}</a>
            </div>
            @if($broadened)
                <p class="-mt-3 mb-6 flex items-center gap-2 text-sm text-muted-strong"><x-icon name="info" class="size-4 text-gold-600" />{{ __('search.smart.broadened', ['words' => $broadened]) }}</p>
            @endif
        @endif
        <x-results :results="$results" :criteria="$criteria" :action="route('search')" :categories="$categories" :municipalities="$municipalities"
            :has-ratings="$hasRatings" :layout="$layout" :favorite-ids="$favoriteIds" />
    </div>
</x-layouts.app>
