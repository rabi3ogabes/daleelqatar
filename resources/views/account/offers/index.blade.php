<x-layouts.account :title="__('account.offers.title')" :subtitle="__('account.offers.subtitle')" :breadcrumbs="[['label' => __('account.offers.title')]]">
    <x-slot:actions>
        @if($eligible)<a href="{{ route('account.offers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('account.offers.create') }}</a>@endif
    </x-slot:actions>

    @if(! $eligible)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-info/20 bg-info-soft p-4 text-sm text-info"><x-icon name="info" class="size-5" />{{ __('account.offers.no_eligible') }}</div>
    @endif

    @if($offers->isEmpty())
        <x-empty-state icon="tag" :title="__('account.offers.empty_title')" :text="__('account.offers.empty_text')">
            @if($eligible)<a href="{{ route('account.offers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('account.offers.create') }}</a>@endif
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <ul class="divide-y divide-navy-50">
                @foreach($offers as $offer)
                    @php
                        $state = $offer->isExpired() ? ['expired', 'badge-muted'] : (! $offer->is_active ? ['paused', 'badge-neutral'] : ($offer->starts_at->isFuture() ? ['scheduled', 'badge-info'] : ($offer->isLive() ? ['live', 'badge-success'] : null)));
                    @endphp
                    <li class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                        <div class="relative aspect-[16/10] w-full shrink-0 overflow-hidden rounded-xl bg-navy-900 sm:w-36">
                            @if($offer->imageUrl())<img src="{{ $offer->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">@else<div class="pattern-lattice absolute inset-0 bg-gradient-to-br from-navy-800 to-navy-950"></div>@endif
                            @if($offer->t('discount_label'))<span class="absolute start-2 top-2 rounded-lg bg-gold-400 px-2 py-0.5 text-xs font-bold text-navy-950">{{ $offer->t('discount_label') }}</span>@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-navy-900">{{ $offer->t('title') }}</p>
                            <p class="mt-0.5 text-xs text-gold-700">{{ $offer->business?->t('name') }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-strong">
                                <x-status-badge :status="$offer->status" />
                                @if($state)<span class="badge {{ $state[1] }}">{{ __('account.offers.'.$state[0]) }}</span>@endif
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" />{{ __('account.offers.period', ['start' => format_date($offer->starts_at), 'end' => format_date($offer->ends_at)]) }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="eye" class="size-3.5" />{{ trans_choice('account.offers.views', $offer->views_count, ['count' => format_number($offer->views_count)]) }}</span>
                            </div>
                            @if($offer->status === \App\Enums\OfferStatus::Rejected && $offer->rejection_reason)
                                <p class="mt-2 rounded-lg bg-danger-soft px-3 py-1.5 text-xs text-danger"><strong>{{ __('account.offers.rejection_reason') }}:</strong> {{ $offer->rejection_reason }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if($offer->isLive())<a href="{{ route('offers.show', $offer) }}" target="_blank" class="btn btn-ghost btn-sm" title="{{ __('account.businesses.view_public') }}"><x-icon name="external-link" class="size-4" /></a>@endif
                            <a href="{{ route('account.offers.edit', $offer->uuid) }}" class="btn btn-outline btn-sm"><x-icon name="pencil" class="size-3.5" />{{ __('common.edit') }}</a>
                            <form method="POST" action="{{ route('account.offers.destroy', $offer->uuid) }}" data-confirm="{{ __('account.offers.delete_confirm') }}">@csrf @method('DELETE')
                                <button class="btn btn-danger-outline btn-sm" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-3.5" /></button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        {{ $offers->links() }}
    @endif
</x-layouts.account>
