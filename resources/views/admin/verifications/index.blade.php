<x-layouts.dashboard area="admin" :title="__('admin.verifications.title')" :subtitle="__('admin.verifications.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.verifications.title')]]">
    @include('admin.partials.request-tabs', ['route' => 'admin.verifications.index', 'status' => $status])
    <x-dash.panel :padding="false">
        @if($requests->isEmpty())
            <x-empty-state icon="badge-check" :title="__('admin.verifications.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('admin.businesses.business') }}</th><th>{{ __('admin.verifications.requested_by') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.status') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($requests as $vr)
                        <tr>
                            <td><span class="font-semibold text-navy-900">{{ $vr->business?->t('name') }}</span><span class="block text-xs text-muted-strong">{{ $vr->business?->category?->t('name') }}</span></td>
                            <td><span class="text-navy-900">{{ $vr->user?->name }}</span><span class="block text-xs text-muted-strong" dir="ltr">{{ $vr->commercial_registration ?? "—" }}</span></td>
                            <td class="text-xs text-muted-strong">{{ format_date($vr->created_at) }}</td>
                            <td><x-status-badge :status="$vr->status" /></td>
                            <td class="text-end"><a href="{{ route('admin.verifications.show', $vr->id) }}" class="btn btn-ghost btn-sm">{{ __('admin.review') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $requests->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
