<x-layouts.account :title="__('account.favorites.title')" :subtitle="__('account.favorites.subtitle')" :breadcrumbs="[['label' => __('account.favorites.title')]]">
    @if($favorites->isEmpty())
        <x-empty-state icon="heart" :title="__('account.favorites.empty_title')" :text="__('account.favorites.empty_text')">
            <a href="{{ route('search') }}" class="btn btn-primary"><x-icon name="compass" class="size-4" />{{ __('nav.explore') }}</a>
        </x-empty-state>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach($favorites as $business)
                <x-business-card :business="$business" :favorited="true" />
            @endforeach
        </div>
        {{ $favorites->links() }}
    @endif
</x-layouts.account>
