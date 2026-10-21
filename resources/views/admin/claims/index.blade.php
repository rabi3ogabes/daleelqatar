<x-layouts.dashboard area="admin" :title="__('admin.claims.title')" :subtitle="__('admin.claims.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.claims.title')]]">
    @include('admin.partials.request-tabs', ['route' => 'admin.claims.index', 'status' => $status])
    <x-dash.panel :padding="false">
        @if($claims->isEmpty())
            <x-empty-state icon="key-round" :title="__('admin.claims.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('admin.businesses.business') }}</th><th>{{ __('admin.claims.claimant') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.status') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($claims as $claim)
                        <tr>
                            <td><span class="font-semibold text-navy-900">{{ $claim->business?->t('name') }}</span><span class="block text-xs text-muted-strong">{{ $claim->business?->category?->t('name') }}</span></td>
                            <td><span class="text-navy-900">{{ $claim->claimant_name }}</span><span class="block text-xs text-muted-strong" dir="ltr">{{ $claim->user?->email }}</span></td>
                            <td class="text-xs text-muted-strong">{{ format_date($claim->created_at) }}</td>
                            <td><x-status-badge :status="$claim->status" /></td>
                            <td class="text-end"><a href="{{ route('admin.claims.show', $claim->id) }}" class="btn btn-ghost btn-sm">{{ __('admin.review') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $claims->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
