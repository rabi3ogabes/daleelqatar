<x-layouts.app>
    <section class="relative overflow-hidden bg-navy-900">
        <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
        <div class="relative container-app py-8 sm:py-10">
            <x-breadcrumbs :items="[['label' => __('search.title')]]" dark />
            <h1 class="mt-4 text-2xl font-bold !text-white sm:text-3xl">
                {{ $criteria->query ? __('search.results_for', ['query' => $criteria->query]) : __('search.title') }}
            </h1>
            <div class="mt-6 max-w-4xl">
                <x-search-form :criteria="$criteria" :categories="$categories" :municipalities="$municipalities" variant="compact" />
            </div>
        </div>
    </section>
    <div class="container-app py-10">
        <x-results :results="$results" :criteria="$criteria" :action="route('search')" :categories="$categories" :municipalities="$municipalities"
            :has-ratings="$hasRatings" :layout="$layout" :favorite-ids="$favoriteIds" />
    </div>
</x-layouts.app>
