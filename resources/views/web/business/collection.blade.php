<x-layouts.app>
    <x-page-hero :title="$title" :subtitle="$text" :icon="$icon" :breadcrumbs="[['label' => $title]]" />
    <div class="container-app py-12">
        @if($results->isNotEmpty())
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($results as $business)
                    <x-business-card :business="$business" :favorited="in_array($business->id, $favoriteIds, true)" :eager="$loop->index < 4" />
                @endforeach
            </div>
            {{ $results->links() }}
        @else
            <x-empty-state :icon="$icon" :title="__('home.empty_latest')">
                <a href="{{ route('add-business') }}" class="btn btn-primary">{{ __('nav.add_business') }}</a>
            </x-empty-state>
        @endif
    </div>
</x-layouts.app>
