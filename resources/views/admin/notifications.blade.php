<x-layouts.dashboard area="admin" :title="__('nav.notifications')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('nav.notifications')]]">
    <x-dash.panel :padding="false">
        <ul class="divide-y divide-navy-50">
            @forelse($notifications as $n)
                @php
                    $d = $n->data;
                    $url = null;
                    if (! empty($d['route'])) { try { $url = route($d['route'], $d['params'] ?? []); } catch (\Throwable) { $url = null; } }
                @endphp
                <li @class(['flex items-start gap-4 px-5 py-4', 'bg-gold-50/40' => ! $n->read_at])>
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon :name="$d['icon'] ?? 'bell'" class="size-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-navy-900">{{ $d['title'] ?? '' }}</p>
                        <p class="text-sm text-muted-strong">{{ $d['body'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $n->created_at?->diffForHumans() }}</p>
                    </div>
                    @if($url)<a href="{{ $url }}" class="btn btn-ghost btn-sm shrink-0">{{ __('common.view_details') }}</a>@endif
                </li>
            @empty
                <li class="p-5"><x-empty-state icon="bell" :title="__('notifications.empty')" /></li>
            @endforelse
        </ul>
        <div class="px-5 pb-5">{{ $notifications->links() }}</div>
    </x-dash.panel>
</x-layouts.dashboard>
