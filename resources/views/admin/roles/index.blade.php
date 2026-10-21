<x-layouts.dashboard area="admin" :title="__('admin.roles.title')" :subtitle="__('admin.roles.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.roles.title')]]">
    <div class="space-y-6">
        @foreach($roles as $role)
            @php $locked = $role->name === \App\Support\Permissions::SUPER_ADMIN; $granted = $role->permissions->pluck('name')->all(); @endphp
            <x-dash.panel :title="__('dashboard.roles.'.$role->name)" :subtitle="__('admin.roles.users_count', ['count' => $role->users_count])">
                @if($locked)
                    <p class="flex items-center gap-2 text-sm text-muted-strong"><x-icon name="lock" class="size-4 text-gold-600" />{{ __('admin.roles.super_admin_hint') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                        @csrf @method('PUT')
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($permissions as $permission)
                                <label class="flex items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-navy-50">
                                    <input type="checkbox" class="checkbox mt-0.5" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $granted, true))>
                                    <span><span class="block text-navy-900">{{ __('admin.permissions.'.str_replace('.', '_', $permission)) }}</span><span class="block font-mono text-[0.68rem] text-muted" dir="ltr">{{ $permission }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-4 flex justify-end"><button class="btn btn-primary">{{ __('common.save_changes') }}</button></div>
                    </form>
                @endif
            </x-dash.panel>
        @endforeach
    </div>
</x-layouts.dashboard>
