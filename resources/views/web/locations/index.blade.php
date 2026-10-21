<x-layouts.app>
    <x-page-hero :title="__('locations.title')" :subtitle="__('locations.subtitle')" icon="map" :breadcrumbs="[['label' => __('nav.locations')]]" />
    <div class="container-app py-12">
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($municipalities as $m)
                <article class="card card-hover flex flex-col overflow-hidden">
                    <a href="{{ route('locations.show', $m) }}" class="pattern-lattice group relative flex h-28 items-end bg-gradient-to-br from-navy-800 to-navy-950 p-5">
                        <span class="absolute end-5 top-5 badge bg-white/10 text-white/80 ring-1 ring-white/15">{{ __('locations.municipality') }}</span>
                        <span>
                            <span class="block text-xl font-bold text-white group-hover:text-gold-200">{{ $m->t('name') }}</span>
                            <span class="text-xs text-white/60">{{ trans_choice('home.businesses_count', $counts[$m->id] ?? 0, ['count' => $counts[$m->id] ?? 0]) }}</span>
                        </span>
                    </a>
                    <div class="flex-1 p-5">
                        <p class="text-xs font-semibold text-muted-strong">{{ __('locations.areas') }}</p>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach($m->areas->take(10) as $area)
                                <li><a href="{{ route('locations.show', [$m, 'area' => $area->slug]) }}" class="inline-block rounded-full bg-navy-50 px-3 py-1 text-xs text-navy-700 ring-1 ring-navy-100 transition hover:bg-gold-50 hover:text-gold-800">{{ $area->t('name') }}</a></li>
                            @endforeach
                            @if($m->areas->count() > 10)<li class="px-2 py-1 text-xs text-muted">+{{ $m->areas->count() - 10 }}</li>@endif
                        </ul>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.app>
