<x-layouts.dashboard area="admin" :title="$user->name" :subtitle="$user->email" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.users.title'), 'url' => route('admin.users.index')], ['label' => $user->name]]">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-dash.panel :title="__('admin.users.profile')">
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs text-muted-strong">{{ __('fields.email') }}</dt><dd class="text-navy-900" dir="ltr">{{ $user->email }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('fields.phone') }}</dt><dd class="text-navy-900" dir="ltr">{{ $user->phone ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.users.language') }}</dt><dd class="text-navy-900">{{ $user->locale === 'en' ? __('common.english') : __('common.arabic') }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.users.email_verified') }}</dt><dd class="text-navy-900">{{ $user->email_verified_at ? format_date($user->email_verified_at) : __('common.no') }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.users.joined') }}</dt><dd class="text-navy-900">{{ format_date($user->created_at) }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.users.last_login') }}</dt><dd class="text-navy-900">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('common.status') }}</dt><dd><x-status-badge :status="$user->status" /></dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.users.api_tokens') }}</dt><dd class="text-navy-900 tabular-nums">{{ $tokens }}</dd></div>
                    @if($user->suspension_reason)<div class="sm:col-span-3"><dt class="text-xs text-muted-strong">{{ __('admin.users.suspension_reason') }}</dt><dd class="text-danger">{{ $user->suspension_reason }}</dd></div>@endif
                </dl>
            </x-dash.panel>

            <x-dash.panel :title="__('nav.my_businesses')" :padding="false">
                <ul class="divide-y divide-navy-50">
                    @forelse($user->businesses as $b)
                        <li><a href="{{ route('admin.businesses.show', $b->id) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gold-50/40">
                            <x-business-cover :business="$b" class="size-10 shrink-0 rounded-lg" sizes="40px" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-navy-900">{{ $b->t('name') }}</span><span class="text-xs text-muted-strong">{{ $b->category?->t('name') }}</span></span>
                            <x-status-badge :status="$b->status" />
                        </a></li>
                    @empty
                        <li class="px-5 py-6 text-sm text-muted-strong">{{ __('admin.users.no_businesses') }}</li>
                    @endforelse
                </ul>
            </x-dash.panel>

            <x-dash.panel :title="__('admin.users.reviews')" :padding="false">
                <ul class="divide-y divide-navy-50">
                    @forelse($reviews as $review)
                        <li class="flex items-start gap-3 px-5 py-3 text-sm">
                            <span class="font-semibold text-gold-700">{{ $review->rating }}★</span>
                            <span class="min-w-0 flex-1"><span class="font-medium text-navy-900">{{ $review->business?->t('name') }}</span><span class="block truncate text-xs text-muted-strong">{{ $review->body }}</span></span>
                            <x-status-badge :status="$review->status" />
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                    @endforelse
                </ul>
            </x-dash.panel>

            @can('audit.view')
                <x-dash.panel :title="__('admin.users.activity')" :padding="false">
                    <ul class="divide-y divide-navy-50">
                        @forelse($activity as $log)
                            <li class="flex items-start gap-3 px-5 py-3 text-sm">
                                <x-icon name="history" class="mt-0.5 size-4 text-gold-600" />
                                <span class="min-w-0 flex-1"><span class="font-medium text-navy-900">{{ \App\Support\AuditLabels::action($log->action) }}</span> <span class="text-muted-strong">— {{ $log->user?->name ?? __('admin.audit.system') }}</span>@if($log->ip_address)<span class="block text-xs text-muted" dir="ltr">{{ $log->ip_address }}</span>@endif</span>
                                <a href="{{ route('admin.audit.show', $log->id) }}" class="shrink-0 text-xs text-gold-700 hover:underline">{{ $log->created_at?->diffForHumans() }}</a>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                        @endforelse
                    </ul>
                </x-dash.panel>
            @endcan
        </div>

        <aside class="space-y-6">
            <x-dash.panel :title="__('admin.users.roles')">
                @can('assignRoles', $user)
                    <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="space-y-3">
                        @csrf @method('PUT')
                        @foreach(\App\Support\Permissions::roles() as $role)
                            @php $manageable = $assignable->contains($role); @endphp
                            <label @class(['flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm', 'border-navy-100' => $manageable, 'border-navy-50 opacity-60' => ! $manageable])>
                                <input type="checkbox" class="checkbox" name="roles[]" value="{{ $role }}" @checked($user->hasRole($role)) @disabled(! $manageable)>
                                <span class="flex-1 text-navy-900">{{ __('dashboard.roles.'.$role) }}</span>
                                @unless($manageable)<x-icon name="lock" class="size-4 text-muted" />@endunless
                            </label>
                        @endforeach
                        <button class="btn btn-primary w-full">{{ __('admin.users.save_roles') }}</button>
                    </form>
                @else
                    <div class="flex flex-wrap gap-1.5">@foreach($user->roles as $role)<span class="badge badge-neutral">{{ __('dashboard.roles.'.$role->name) }}</span>@endforeach</div>
                    <p class="mt-3 text-xs text-muted-strong">{{ auth()->user()->is($user) ? __('admin.users.cannot_edit_own_roles') : __('admin.users.cannot_edit_roles') }}</p>
                @endcan
            </x-dash.panel>

            @can('suspend', $user)
                <x-dash.panel :title="__('admin.users.account_access')">
                    @if($user->isSuspended())
                        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">@csrf
                            <button class="btn btn-primary w-full"><x-icon name="rotate-ccw" class="size-4" />{{ __('admin.users.unsuspend') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-3" data-confirm="{{ __('admin.users.confirm_suspend') }}">
                            @csrf
                            <x-form.textarea name="reason" rows="2" :label="__('admin.users.suspension_reason')" required maxlength="500" />
                            <button class="btn btn-danger-outline w-full"><x-icon name="ban" class="size-4" />{{ __('admin.users.suspend') }}</button>
                        </form>
                    @endif
                </x-dash.panel>
            @endcan

            @can('sendPasswordReset', $user)
                <x-dash.panel :title="__('admin.users.password')">
                    <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">@csrf
                        <p class="mb-3 text-xs text-muted-strong">{{ __('admin.users.password_hint') }}</p>
                        <button class="btn btn-outline w-full"><x-icon name="mail" class="size-4" />{{ __('admin.users.send_reset') }}</button>
                    </form>
                </x-dash.panel>
            @endcan
        </aside>
    </div>
</x-layouts.dashboard>
