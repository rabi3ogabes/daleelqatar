@php $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '∅')); @endphp
<x-layouts.dashboard area="admin" :title="\App\Support\AuditLabels::action($log->action)" :subtitle="$log->action" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.audit.title'), 'url' => route('admin.audit.index')], ['label' => '#'.$log->id]]">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <x-dash.panel :title="__('admin.audit.changes')" :padding="false">
            @if($keys)
                <x-dash.table>
                    <thead><tr><th>{{ __('admin.audit.field') }}</th><th>{{ __('admin.audit.before') }}</th><th>{{ __('admin.audit.after') }}</th></tr></thead>
                    <tbody>
                        @foreach($keys as $k)
                            <tr>
                                <td class="font-mono text-xs text-navy-900" dir="ltr">{{ $k }}</td>
                                <td class="max-w-sm align-top"><pre class="text-xs whitespace-pre-wrap text-danger/90" dir="auto">{{ $fmt($log->old_values[$k] ?? null) }}</pre></td>
                                <td class="max-w-sm align-top"><pre class="text-xs whitespace-pre-wrap text-success" dir="auto">{{ $fmt($log->new_values[$k] ?? null) }}</pre></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-dash.table>
            @else
                <p class="px-5 py-8 text-center text-sm text-muted-strong">{{ __('admin.audit.no_changes') }}</p>
            @endif
        </x-dash.panel>
        <aside class="space-y-6">
            <x-dash.panel :title="__('common.details')">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.audit.actor') }}</dt><dd class="text-navy-900">{{ $log->user?->name ?? __('admin.audit.system') }} @if($log->user)<span class="block text-xs text-muted-strong" dir="ltr">{{ $log->user->email }}</span>@endif</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('common.date') }}</dt><dd class="text-navy-900" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.audit.subject') }}</dt><dd class="text-navy-900">{{ \App\Support\AuditLabels::subject($log->auditable_type) }}@if($log->auditable_id) #{{ $log->auditable_id }}@endif</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.audit.ip') }}</dt><dd class="font-mono text-xs text-navy-900" dir="ltr">{{ $log->ip_address ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.audit.agent') }}</dt><dd class="text-xs break-all text-muted-strong" dir="ltr">{{ $log->user_agent ?? '—' }}</dd></div>
                </dl>
            </x-dash.panel>
            @if($log->metadata)
                <x-dash.panel :title="__('admin.audit.metadata')"><pre class="text-xs whitespace-pre-wrap text-navy-900" dir="auto">{{ $fmt($log->metadata) }}</pre></x-dash.panel>
            @endif
            @if($log->auditable_type === 'business' && $log->auditable_id)
                <a href="{{ route('admin.businesses.show', $log->auditable_id) }}" class="btn btn-outline w-full">{{ __('admin.audit.open_business') }}</a>
            @elseif($log->auditable_type === 'user' && $log->auditable_id)
                <a href="{{ route('admin.users.show', $log->auditable_id) }}" class="btn btn-outline w-full">{{ __('admin.audit.open_user') }}</a>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
