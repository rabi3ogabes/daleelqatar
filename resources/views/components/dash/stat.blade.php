@props(['label', 'value', 'icon' => 'activity', 'sublabel' => null, 'trend' => null, 'href' => null])
{{-- $trend: percentage change vs previous period computed from real data, or null when not computable --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['card relative flex items-start justify-between gap-4 p-5', 'card-hover' => $href]) }}>
    <div class="min-w-0">
        <p class="text-sm text-muted-strong">{{ $label }}</p>
        <p class="mt-2 text-[1.75rem] leading-none font-bold text-navy-900 tabular-nums">{{ is_numeric($value) ? format_number($value) : $value }}</p>
        @if($trend !== null)
            <p @class(['mt-2 inline-flex items-center gap-1 text-xs font-semibold', 'text-success' => $trend >= 0, 'text-danger' => $trend < 0])>
                <x-icon :name="$trend >= 0 ? 'trending-up' : 'arrow-down'" class="size-3.5" />{{ $trend >= 0 ? '+' : '' }}{{ $trend }}%
                <span class="font-normal text-muted">{{ __('dashboard.vs_previous') }}</span>
            </p>
        @elseif($sublabel)
            <p class="mt-2 text-xs text-muted-strong">{{ $sublabel }}</p>
        @endif
    </div>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-gold-200 bg-gold-50 text-gold-700">
        <x-icon :name="$icon" class="size-5" />
    </span>
</{{ $tag }}>
