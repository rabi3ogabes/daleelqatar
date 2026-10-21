<x-layouts.dashboard area="admin" :title="__('admin.audit.title')" :subtitle="__('admin.audit.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.audit.title')]]">
    <x-dash.filters :action="route('admin.audit.index')">
        <div class="w-44"><label class="label" for="a-action">{{ __('admin.audit.action') }}</label>
            <select id="a-action" name="action" class="select"><option value="">{{ __('common.all') }}</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ \App\Support\AuditLabels::subject($a) }}</option>@endforeach</select></div>
        <div class="w-44"><label class="label" for="a-subject">{{ __('admin.audit.subject') }}</label>
            <select id="a-subject" name="subject" class="select"><option value="">{{ __('common.all') }}</option>@foreach($subjects as $s)<option value="{{ $s }}" @selected(request('subject') === $s)>{{ \App\Support\AuditLabels::subject($s) }}</option>@endforeach</select></div>
        <div class="w-32"><label class="label" for="a-user">{{ __('admin.audit.user_id') }}</label><input id="a-user" type="number" name="user" value="{{ request('user') }}" class="input" dir="ltr"></div>
        <div class="w-40"><label class="label" for="a-from">{{ __('admin.audit.from') }}</label><input id="a-from" type="date" name="from" value="{{ request('from') }}" class="input"></div>
        <div class="w-40"><label class="label" for="a-to">{{ __('admin.audit.to') }}</label><input id="a-to" type="date" name="to" value="{{ request('to') }}" class="input"></div>
    </x-dash.filters>
    <x-dash.panel :padding="false">
        @if($logs->isEmpty())
            <x-empty-state icon="file-clock" :title="__('dashboard.no_data')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('admin.audit.actor') }}</th><th>{{ __('admin.audit.action') }}</th><th>{{ __('admin.audit.subject') }}</th><th>{{ __('admin.audit.ip') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="text-xs whitespace-nowrap text-muted-strong" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-navy-900">{{ $log->user?->name ?? __('admin.audit.system') }}</td>
                            <td><span class="font-medium text-navy-900">{{ \App\Support\AuditLabels::action($log->action) }}</span><span class="block font-mono text-[0.68rem] text-muted" dir="ltr">{{ $log->action }}</span></td>
                            <td class="text-muted-strong">{{ \App\Support\AuditLabels::subject($log->auditable_type) }}@if($log->auditable_id) #{{ $log->auditable_id }}@endif</td>
                            <td class="font-mono text-xs text-muted" dir="ltr">{{ $log->ip_address }}</td>
                            <td class="text-end"><a href="{{ route('admin.audit.show', $log) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $logs->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
