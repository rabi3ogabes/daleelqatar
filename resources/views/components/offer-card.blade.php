@props(['offer'])
@php
    $days = (int) now()->startOfDay()->diffInDays($offer->ends_at->copy()->startOfDay());
@endphp
<article {{ $attributes->class('group card card-hover relative flex flex-col overflow-hidden') }}>
    <div class="relative aspect-[16/9] overflow-hidden bg-navy-900">
        @if($offer->imageUrl())
            <img src="{{ $offer->imageUrl() }}" alt="{{ $offer->t('title') }}" loading="lazy" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.04]">
        @else
            <div class="pattern-lattice absolute inset-0 bg-gradient-to-br from-navy-800 to-navy-950"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(215,184,117,0.28),transparent_60%)]"></div>
        @endif
        @if($offer->t('discount_label'))
            <span class="absolute start-4 top-4 rounded-xl bg-gold-400 px-3 py-1.5 text-sm font-bold text-navy-950 shadow-gold">{{ $offer->t('discount_label') }}</span>
        @endif
        <span class="absolute bottom-3 end-3 badge bg-navy-950/75 text-white backdrop-blur"><x-icon name="clock" class="size-3" />{{ trans_choice('offers.ends_in_days', $days, ['count' => $days]) }}</span>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <h3 class="font-semibold text-navy-900"><a href="{{ route('offers.show', $offer) }}" class="line-clamp-2 after:absolute after:inset-0 hover:text-gold-800">{{ $offer->t('title') }}</a></h3>
        <p class="mt-1 text-xs text-gold-700">{{ __('offers.by', ['business' => $offer->business?->t('name')]) }}</p>
        @if($offer->t('description'))<p class="mt-2 line-clamp-2 text-sm text-muted-strong">{{ $offer->t('description') }}</p>@endif
        <p class="mt-auto pt-3 text-xs text-muted">{{ __('offers.valid_until', ['date' => format_date($offer->ends_at)]) }}</p>
    </div>
</article>
