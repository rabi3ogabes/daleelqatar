<x-layouts.dashboard area="admin" :title="__('admin.messages.title')" :subtitle="__('admin.messages.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.messages.title')]]">
    <nav class="mb-4 flex gap-1 overflow-x-auto scrollbar-none">
        <a href="{{ route('admin.messages.index') }}" @class(['btn btn-sm', 'btn-primary' => ! $status, 'btn-ghost' => $status])>{{ __('admin.messages.inbox') }}</a>
        @foreach(\App\Http\Controllers\Admin\MessageController::STATUSES as $s)
            <a href="{{ route('admin.messages.index', ['status' => $s]) }}" @class(['btn btn-sm', 'btn-primary' => $status === $s, 'btn-ghost' => $status !== $s])>{{ __('admin.messages.statuses.'.$s) }} <span class="opacity-60">{{ $counts[$s] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <x-dash.filters :action="route('admin.messages.index')">
        @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="min-w-56 flex-1"><label class="label" for="m-q">{{ __('common.search') }}</label><input id="m-q" type="search" name="q" value="{{ request('q') }}" class="input"></div>
    </x-dash.filters>
    <x-dash.panel :padding="false">
        <ul class="divide-y divide-navy-50">
            @forelse($messages as $m)
                <li>
                    <a href="{{ route('admin.messages.show', $m) }}" class="flex items-start gap-4 px-5 py-4 hover:bg-gold-50/40">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-gold-500' => $m->status === 'new', 'bg-transparent' => $m->status !== 'new'])></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block truncate text-sm text-navy-900', 'font-semibold' => $m->status === 'new'])>{{ $m->subject }}</span>
                            <span class="block truncate text-xs text-muted-strong">{{ $m->name }} · <span dir="ltr">{{ $m->email }}</span> — {{ \Illuminate\Support\Str::limit($m->message, 90) }}</span>
                        </span>
                        <span class="shrink-0 text-end text-xs text-muted"><span class="badge badge-neutral mb-1">{{ __('admin.messages.statuses.'.$m->status) }}</span><br>{{ $m->created_at?->diffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li class="p-5"><x-empty-state icon="inbox" :title="__('admin.messages.empty')" /></li>
            @endforelse
        </ul>
        <div class="px-5 pb-5">{{ $messages->links() }}</div>
    </x-dash.panel>
</x-layouts.dashboard>
