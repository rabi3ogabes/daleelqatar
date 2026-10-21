<x-layouts.account :title="__('account.notifications.title')" :subtitle="__('account.notifications.subtitle')" :breadcrumbs="[['label' => __('account.notifications.title')]]">
    @if(auth()->user()->unreadNotifications()->exists())
        <x-slot:actions>
            <form method="POST" action="{{ route('account.notifications.read-all') }}">@csrf
                <button class="btn btn-outline"><x-icon name="check" class="size-4" />{{ __('notifications.mark_all_read') }}</button>
            </form>
        </x-slot:actions>
    @endif

    @if($notifications->isEmpty())
        <x-empty-state icon="bell" :title="__('notifications.empty')" />
    @else
        <div class="card">
            <ul class="space-y-1 p-2">
                @foreach($notifications as $notification)
                    @include('account.partials.notification-item')
                @endforeach
            </ul>
        </div>
        {{ $notifications->links() }}
    @endif
</x-layouts.account>
