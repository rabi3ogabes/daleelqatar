@props(['segments' => [], 'title', 'centerLabel' => null, 'max' => 5, 'stacked' => false])
{{-- Donut with legend. $segments: [['label' => …, 'value' => int]]. Beyond $max slices fold into "Other". --}}
@php
    $palette = \App\Support\ChartPalette::CATEGORICAL;
    $sorted = collect($segments)->filter(fn ($s) => $s['value'] > 0)->sortByDesc('value')->values();
    $main = $sorted->take($max);
    $rest = $sorted->slice($max)->sum('value');
    if ($rest > 0) { $main->push(['label' => __('dashboard.other'), 'value' => $rest, 'other' => true]); }
    $total = max(1, $main->sum('value'));
    $r = 15.9155; $c = 2 * M_PI * $r; $offset = 0;
    $slices = $main->values()->map(function ($s, $i) use ($palette, $total, $c, &$offset) {
        $len = $c * $s['value'] / $total;
        $slice = $s + ['color' => ! empty($s['other']) ? \App\Support\ChartPalette::OTHER : $palette[$i % count($palette)], 'dash' => max(0, $len - 0.6), 'offset' => -$offset, 'pct' => round(100 * $s['value'] / $total)];
        $offset += $len;
        return $slice;
    });
@endphp
<figure {{ $attributes->class(['flex flex-col items-center gap-6', 'sm:flex-row' => ! $stacked]) }} x-data="{ h: null }">
    <figcaption class="sr-only">{{ $title }}</figcaption>
    <div class="relative size-40 shrink-0">
        <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="{{ $title }}">
            <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="#F4F6F9" stroke-width="6"/>
            @foreach($slices as $i => $s)
                <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="6" stroke-dasharray="{{ $s['dash'] }} {{ $c }}" stroke-dashoffset="{{ $s['offset'] }}" class="transition-opacity" :class="h !== null && h !== {{ $i }} ? 'opacity-30' : ''" @mouseenter="h = {{ $i }}" @mouseleave="h = null"/>
            @endforeach
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-2xl font-bold text-navy-900">{{ format_number($sorted->sum('value')) }}</span>
            @if($centerLabel)<span class="text-[0.7rem] text-muted-strong">{{ $centerLabel }}</span>@endif
        </div>
    </div>
    <ul class="w-full flex-1 space-y-2.5 text-sm">
        @forelse($slices as $i => $s)
            <li class="flex items-center gap-2.5 rounded-lg px-1 transition" :class="h === {{ $i }} ? 'bg-navy-50' : ''" @mouseenter="h = {{ $i }}" @mouseleave="h = null">
                <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $s['color'] }}"></span>
                <span class="min-w-0 flex-1 truncate text-charcoal">{{ $s['label'] }}</span>
                <span class="text-muted-strong tabular-nums">{{ format_number($s['value']) }}</span>
                <span class="w-10 text-end font-semibold text-navy-900 tabular-nums">{{ $s['pct'] }}%</span>
            </li>
        @empty
            <li class="text-muted-strong">{{ __('dashboard.no_data') }}</li>
        @endforelse
    </ul>
</figure>
