<x-layouts.app>
    <x-page-hero :title="__('locations.businesses_in', ['location' => $municipality->t('name')])" :subtitle="$municipality->t('description') ?: __('locations.meta_description', ['location' => $municipality->t('name')])" icon="map-pin"
        :breadcrumbs="[['label' => __('nav.locations'), 'url' => route('locations.index')], ['label' => $municipality->t('name')]]">
        @if($municipality->areas->isNotEmpty())
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('locations.areas') }}">
                <a href="{{ route('locations.show', $municipality) }}" @class(['rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition', 'bg-gold-400 text-navy-950 ring-gold-400' => ! $criteria->area, 'bg-white/5 text-white/80 ring-white/15 hover:bg-white/15' => $criteria->area])>{{ __('common.all') }}</a>
                @foreach($municipality->areas as $area)
                    <a href="{{ route('locations.show', [$municipality, 'area' => $area->slug]) }}" @class(['rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition', 'bg-gold-400 text-navy-950 ring-gold-400' => $criteria->area?->is($area), 'bg-white/5 text-white/80 ring-white/15 hover:bg-white/15' => ! $criteria->area?->is($area)])>{{ $area->t('name') }}</a>
                @endforeach
            </nav>
        @endif
    </x-page-hero>
    <div class="container-app py-10">
        <x-results :results="$results" :criteria="$criteria" :action="route('locations.show', $municipality)" :show-municipality="false" :fixed-municipality="$municipality"
            :categories="$categories" :layout="request('view') === 'list' ? 'list' : 'grid'" :favorite-ids="$favoriteIds" />
    </div>
</x-layouts.app>
