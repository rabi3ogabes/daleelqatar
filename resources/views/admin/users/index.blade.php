<x-layouts.dashboard area="admin" :title="__('admin.users.title')" :subtitle="__('admin.users.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.users.title')]]">
    <x-dash.filters :action="route('admin.users.index')">
        <div class="min-w-56 flex-1"><label class="label" for="u-q">{{ __('common.search') }}</label><input id="u-q" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="{{ __('admin.users.search_placeholder') }}"></div>
        <div class="w-48"><label class="label" for="u-role">{{ __('admin.users.role') }}</label>
            <select id="u-role" name="role" class="select"><option value="">{{ __('common.all') }}</option>@foreach(\App\Support\Permissions::roles() as $r)<option value="{{ $r }}" @selected(request('role') === $r)>{{ __('dashboard.roles.'.$r) }}</option>@endforeach</select></div>
        <div class="w-40"><label class="label" for="u-status">{{ __('common.status') }}</label>
            <select id="u-status" name="status" class="select"><option value="">{{ __('common.all') }}</option>@foreach(\App\Enums\UserStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="w-40"><label class="label" for="u-ver">{{ __('admin.users.email_verified') }}</label>
            <select id="u-ver" name="verified" class="select"><option value="">{{ __('common.all') }}</option><option value="0" @selected(request('verified') === '0')>{{ __('admin.users.unverified') }}</option></select></div>
    </x-dash.filters>
    <x-dash.panel :padding="false">
        @if($users->isEmpty())
            <x-empty-state icon="users" :title="__('admin.users.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('admin.users.user') }}</th><th>{{ __('admin.users.roles') }}</th><th>{{ __('nav.my_businesses') }}</th><th>{{ __('common.status') }}</th><th>{{ __('admin.users.joined') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-semibold text-gold-300">{{ $u->initials() }}</span>
                                    <span class="min-w-0"><span class="block font-medium text-navy-900">{{ $u->name }}</span><span class="block text-xs text-muted-strong" dir="ltr">{{ $u->email }}</span></span>
                                    @unless($u->email_verified_at)<span class="badge badge-warning">{{ __('admin.users.unverified') }}</span>@endunless
                                </div>
                            </td>
                            <td><div class="flex flex-wrap gap-1">@forelse($u->roles as $role)<span class="badge {{ in_array($role->name, \App\Support\Permissions::STAFF_ROLES, true) ? 'badge-verified' : 'badge-neutral' }}">{{ __('dashboard.roles.'.$role->name) }}</span>@empty<span class="text-xs text-muted">—</span>@endforelse</div></td>
                            <td class="tabular-nums">{{ $u->businesses_count }}</td>
                            <td><x-status-badge :status="$u->status" /></td>
                            <td class="text-xs text-muted-strong">{{ format_date($u->created_at) }}</td>
                            <td class="text-end"><a href="{{ route('admin.users.show', $u) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $users->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
