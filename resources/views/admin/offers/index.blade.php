<x-layouts.dashboard area="admin" :title="__('admin.offers.title')" :subtitle="__('admin.offers.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.offers.title')]]">
    <x-slot:actions>@can('offers.manage')<a href="{{ route('admin.offers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.offers.create') }}</a>@endcan</x-slot:actions>
    @include('admin.partials.status-tabs', ['route' => 'admin.offers.index', 'cases' => \App\Enums\OfferStatus::cases(), 'current' => $status, 'counts' => $counts])
    <div class="mb-4 flex gap-1">
        @foreach(['' => __('common.all'), 'live' => __('admin.offers.live'), 'expired' => __('admin.offers.expired')] as $key => $label)
            <a href="{{ route('admin.offers.index', array_filter(['status' => $status, 'timing' => $key])) }}" @class(['btn btn-sm', 'btn-outline' => request('timing', '') === $key, 'btn-ghost' => request('timing', '') !== $key])>{{ $label }}</a>
        @endforeach
    </div>
    <x-dash.panel :padding="false">
        @if($offers->isEmpty())
            <x-empty-state icon="tag" :title="__('admin.offers.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('admin.offers.offer') }}</th><th>{{ __('admin.businesses.business') }}</th><th>{{ __('admin.offers.period') }}</th><th>{{ __('common.status') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($offers as $offer)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($offer->imageUrl())<img src="{{ $offer->imageUrl() }}" alt="" class="size-11 rounded-lg object-cover">@else<span class="flex size-11 items-center justify-center rounded-lg bg-navy-900 text-xs font-bold text-gold-300">{{ $offer->t('discount_label') ?: '%' }}</span>@endif
                                    <span class="min-w-0"><span class="block max-w-64 truncate font-semibold text-navy-900">{{ $offer->t('title') }}</span>
                                        <span class="flex gap-1">@unless($offer->is_active)<span class="badge badge-muted">{{ __('admin.inactive') }}</span>@endunless @if($offer->is_featured)<span class="badge badge-gold">{{ __('admin.featured') }}</span>@endif @if($offer->isExpired())<span class="badge badge-muted">{{ __('admin.offers.expired') }}</span>@endif</span>
                                    </span>
                                </div>
                            </td>
                            <td class="text-muted-strong">{{ $offer->business?->t('name') }}</td>
                            <td class="text-xs whitespace-nowrap text-muted-strong">{{ format_date($offer->starts_at) }} → {{ format_date($offer->ends_at) }}</td>
                            <td><x-status-badge :status="$offer->status" />@if($offer->rejection_reason)<span class="mt-1 block max-w-48 truncate text-xs text-danger" title="{{ $offer->rejection_reason }}">{{ $offer->rejection_reason }}</span>@endif</td>
                            <td class="text-end">
                                <div class="flex flex-wrap items-center justify-end gap-1" x-data="{ reject: false }">
                                    @can('offers.moderate')
                                        @if($offer->status !== \App\Enums\OfferStatus::Approved)
                                            <form method="POST" action="{{ route('admin.offers.moderate', $offer) }}">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-primary btn-sm">{{ __('admin.approve') }}</button></form>
                                        @endif
                                        @if($offer->status !== \App\Enums\OfferStatus::Rejected)
                                            <button type="button" class="btn btn-outline btn-sm" @click="reject = !reject">{{ __('admin.reject') }}</button>
                                            <form x-show="reject" x-cloak method="POST" action="{{ route('admin.offers.moderate', $offer) }}" class="order-last w-full max-w-72 space-y-2 rounded-xl border border-navy-100 bg-white p-3 text-start shadow-soft">
                                                @csrf<input type="hidden" name="status" value="rejected">
                                                <textarea name="reason" rows="2" required maxlength="1000" class="textarea min-h-16" placeholder="{{ __('admin.reject_reason') }}"></textarea>
                                                <button class="btn btn-danger btn-sm w-full">{{ __('admin.confirm_reject') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('offers.manage')
                                        <a href="{{ route('admin.offers.edit', $offer) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                                        <form method="POST" action="{{ route('admin.offers.destroy', $offer) }}" data-confirm="{{ __('admin.offers.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button></form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $offers->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
