<x-layouts.app>
    <x-page-hero :title="__('guides.index_title')" :subtitle="__('guides.index_description')" icon="book-marked" :breadcrumbs="[['label' => __('guides.index_title')]]" />
    <div class="container-app py-12">
        @if($guides->isEmpty())
            <x-empty-state icon="book-marked" :title="__('guides.empty_title')" :text="__('guides.empty_text')">
                <a href="{{ route('categories.index') }}" class="btn btn-primary">{{ __('search.browse_categories') }}</a>
            </x-empty-state>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($guides as $guide)
                    <a href="{{ route('guides.show', $guide) }}" class="card card-hover group flex flex-col overflow-hidden">
                        <div class="relative h-28 overflow-hidden bg-navy-900">
                            <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
                            <span class="absolute bottom-4 start-5 flex size-12 items-center justify-center rounded-2xl border border-gold-400/30 bg-white/5 text-gold-300"><x-icon :name="$guide->category?->icon ?: ($guide->open_24h ? 'clock' : 'book-marked')" class="size-6" /></span>
                            @if($guide->open_24h)<span class="absolute end-4 top-4 rounded-full bg-gold-400 px-2.5 py-1 text-xs font-bold text-navy-950">24/7</span>@endif
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="font-semibold text-navy-900 transition group-hover:text-gold-800">{{ $guide->t('title') }}</h2>
                            <p class="mt-2 line-clamp-3 text-sm text-muted-strong">{{ $guide->t('intro') }}</p>
                            <p class="mt-auto pt-4 text-xs text-muted">{{ collect([$guide->category?->t('name'), $guide->area?->t('name') ?? $guide->municipality?->t('name')])->filter()->implode(' · ') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
