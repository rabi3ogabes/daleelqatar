@props(['labels' => [], 'series' => [], 'title', 'height' => 240])
{{-- Multi-series line chart on one axis (same unit). $series: [['label' => …, 'values' => [...], 'color' => '#hex', 'area' => bool]].
     Legend above, crosshair with every series' value on hover, data table for assistive tech. --}}
@php
    $n = count($labels);
    $W = 640; $H = (int) $height; $padL = 40; $padR = 12; $padT = 14; $padB = 28;
    $all = collect($series)->flatMap(fn ($s) => $s['values']);
    [$max, $step] = \App\Support\ChartPalette::niceScale((float) ($all->max() ?? 0));
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $x = fn ($i) => $padL + ($n > 1 ? $plotW * $i / ($n - 1) : $plotW / 2);
    $y = fn ($v) => $padT + $plotH - ($max > 0 ? $plotH * $v / $max : 0);
    $every = max(1, (int) ceil($n / 8));
    $gid = 'ls'.\Illuminate\Support\Str::random(5);
    $points = collect($labels)->values()->map(fn ($l, $i) => [
        'l' => $l,
        'x' => round($x($i) / $W * 100, 2),
        'y' => round(min(array_map(fn ($s) => $y($s['values'][$i] ?? 0), $series ?: [['values' => [0]]])) / $H * 100, 2),
        'v' => collect($series)->map(fn ($s) => ['c' => $s['color'], 'n' => $s['label'], 'v' => format_number($s['values'][$i] ?? 0), 'y' => round($y($s['values'][$i] ?? 0), 1)])->all(),
    ]);
@endphp
<figure {{ $attributes->class('relative') }} x-data="{ i: null, pts: @js($points) }">
    <figcaption class="sr-only">{{ $title }}</figcaption>
    @if(count($series) > 1)
        <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted-strong">
            @foreach($series as $s)<span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span>@endforeach
        </div>
    @endif
    <div class="relative" dir="ltr">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full overflow-visible" role="img" aria-label="{{ $title }}" @mouseleave="i = null">
            <defs>
                @foreach($series as $k => $s)
                    <linearGradient id="{{ $gid.$k }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{{ $s['color'] }}" stop-opacity=".18"/><stop offset="1" stop-color="{{ $s['color'] }}" stop-opacity="0"/></linearGradient>
                @endforeach
            </defs>
            @for($t = 0; $t <= $max + 0.0001; $t += $step)
                <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" stroke="#E9ECF2" stroke-width="1" @if($t > 0) stroke-dasharray="3 4" @endif/>
                <text x="{{ $padL - 8 }}" y="{{ $y($t) + 4 }}" text-anchor="end" font-size="11" fill="#5F6672">{{ format_number($t) }}</text>
            @endfor
            @foreach($series as $k => $s)
                @php $line = collect($s['values'])->values()->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' '); @endphp
                @if($n && ($s['area'] ?? false))
                    <path d="M{{ round($x(0), 1) }},{{ $padT + $plotH }} L{{ str_replace(' ', ' L', $line) }} L{{ round($x($n - 1), 1) }},{{ $padT + $plotH }} Z" fill="url(#{{ $gid.$k }})"/>
                @endif
                @if($n)<polyline points="{{ $line }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>@endif
            @endforeach
            @foreach($labels as $i => $label)
                @if($i % $every === 0 || ($i === $n - 1 && ($n - 1) % $every >= $every / 2))
                    <text x="{{ $x($i) }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#5F6672">{{ $label }}</text>
                @endif
            @endforeach
            {{-- No <template> inside SVG: browsers render its children as SVG. A hidden group follows the pointer instead. --}}
            <g x-show="i !== null" style="display: none">
                <line :x1="i === null ? 0 : pts[i].x * {{ $W }} / 100" :x2="i === null ? 0 : pts[i].x * {{ $W }} / 100" x1="0" x2="0" y1="{{ $padT }}" y2="{{ $padT + $plotH }}" stroke="#AEB8CC" stroke-width="1"/>
                @foreach($series as $k => $s)
                    <circle :cx="i === null ? 0 : pts[i].x * {{ $W }} / 100" :cy="i === null ? 0 : pts[i].v[{{ $k }}].y" cx="0" cy="0" r="5" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="2"/>
                @endforeach
            </g>
            @foreach($labels as $i => $label)
                <rect x="{{ $x($i) - $plotW / max(1, $n - 1) / 2 }}" y="{{ $padT }}" width="{{ $plotW / max(1, $n - 1) }}" height="{{ $plotH }}" fill="transparent" @mouseenter="i = {{ $i }}"/>
            @endforeach
        </svg>
        <div x-show="i !== null" x-cloak class="pointer-events-none absolute z-10 min-w-32 -translate-x-1/2 -translate-y-full rounded-lg bg-navy-900 px-3 py-2 text-xs text-white shadow-lift" :style="i !== null && `left:${Math.min(88, Math.max(12, pts[i].x))}%;top:calc(${pts[i].y}% - 10px)`">
            <span class="mb-1 block text-white/60" x-text="pts[i]?.l"></span>
            <template x-for="s in (pts[i]?.v || [])">
                <span class="flex items-center justify-between gap-3"><span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" :style="`background:${s.c}`"></span><span x-text="s.n"></span></span><span class="font-semibold tabular-nums" x-text="s.v"></span></span>
            </template>
        </div>
    </div>
    <table class="sr-only">
        <caption>{{ $title }}</caption>
        <thead><tr><th scope="col"></th>@foreach($series as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach</tr></thead>
        <tbody>@foreach($labels as $i => $label)<tr><th scope="row">{{ $label }}</th>@foreach($series as $s)<td>{{ $s['values'][$i] ?? 0 }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</figure>
