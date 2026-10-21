<x-layouts.app>
    <x-page-hero :title="__('offers.title')" :subtitle="__('offers.subtitle')" icon="tag" :breadcrumbs="[['label' => __('nav.offers')]]" />
    <div class="container-app py-12">
        @if($offers->isNotEmpty())
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($offers as $offer)<x-offer-card :offer="$offer" />@endforeach
            </div>
            {{ $offers->links() }}
        @else
            <x-empty-state icon="tag" :title="__('offers.empty')">
                <a href="{{ route('search') }}" class="btn btn-primary">{{ __('nav.explore') }}</a>
            </x-empty-state>
        @endif
    </div>
</x-layouts.app>
