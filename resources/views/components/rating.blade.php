@props(['value' => null, 'count' => 0, 'size' => 'sm', 'showCount' => true])
@if($value !== null && $count > 0)
    @php $px = $size === 'lg' ? 'size-5' : 'size-3.5'; @endphp
    <span {{ $attributes->class('inline-flex items-center gap-1.5') }}>
        <span class="flex items-center" aria-hidden="true">
            @for($i = 1; $i <= 5; $i++)
                <svg viewBox="0 0 24 24" class="{{ $px }} {{ $value >= $i - 0.25 ? 'text-gold-500' : ($value >= $i - 0.75 ? 'text-gold-300' : 'text-navy-100') }}" fill="currentColor"><path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96z"/></svg>
            @endfor
        </span>
        <span class="text-xs font-semibold text-navy-900">{{ number_format($value, 1) }}</span>
        @if($showCount)<span class="text-xs text-muted-strong">({{ format_number($count) }})</span>@endif
        <span class="sr-only">{{ __('business.rating_out_of', ['rating' => number_format($value, 1)]) }}, {{ trans_choice('business.reviews_count', $count, ['count' => $count]) }}</span>
    </span>
@endif
