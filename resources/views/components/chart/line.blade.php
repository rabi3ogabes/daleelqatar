@props(['labels' => [], 'values' => [], 'title', 'height' => 220, 'color' => \App\Support\ChartPalette::PRIMARY, 'valueLabel' => ''])
{{-- Single-series line/area chart. Server-rendered SVG, crosshair tooltip on hover, data table for assistive tech. --}}
@php
    $n = count($values);
    $W = 640; $H = (int) $height; $padL = 40; $padR = 12; $padT = 14; $padB = 28;
    [$max, $step] = \App\Support\ChartPalette::niceScale((float) (max($values ?: [0])));
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $x = fn ($i) => $padL + ($n > 1 ? $plotW * $i / ($n - 1) : $plotW / 2);
    $y = fn ($v) => $padT + $plotH - ($max > 0 ? $plotH * $v / $max : 0);
    $points = collect($values)->values()->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
    $area = $n ? 'M'.round($x(0), 1).','.($padT + $plotH).' L'.str_replace(' ', ' L', $points).' L'.round($x($n - 1), 1).','.($padT + $plotH).' Z' : '';
    $every = max(1, (int) ceil($n / 8));
    $gid = 'lg'.\Illuminate\Support\Str::random(5);
    $data = collect($values)->values()->map(fn ($v, $i) => ['l' => $labels[$i] ?? '', 'v' => format_number($v), 'x' => round($x($i) / $W * 100, 2), 'y' => round($y($v) / $H * 100, 2)]);
@endphp
<figure {{ $attributes->class('relative') }} x-data="{ i: null, pts: @js($data) }">
    <figcaption class="sr-only">{{ $title }}</figcaption>
    <div class="relative" dir="ltr">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full overflow-visible" role="img" aria-label="{{ $title }}" @mouseleave="i = null">
            <defs><linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{{ $color }}" stop-opacity=".22"/><stop offset="1" stop-color="{{ $color }}" stop-opacity="0"/></linearGradient></defs>
            @for($t = 0; $t <= $max + 0.0001; $t += $step)
                <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" stroke="#E9ECF2" stroke-width="1" @if($t > 0) stroke-dasharray="3 4" @endif/>
                <text x="{{ $padL - 8 }}" y="{{ $y($t) + 4 }}" text-anchor="end" font-size="11" fill="#5F6672">{{ format_number($t) }}</text>
            @endfor
            @if($n)
                <path d="{{ $area }}" fill="url(#{{ $gid }})"/>
                <polyline points="{{ $points }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            @endif
            @foreach($labels as $i => $label)
                @if($i % $every === 0 || ($i === $n - 1 && ($n - 1) % $every >= $every / 2))
                    <text x="{{ $x($i) }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#5F6672">{{ $label }}</text>
                @endif
            @endforeach
            {{-- No <template> inside SVG: browsers render its children as SVG. A hidden group follows the pointer instead. --}}
            <g x-show="i !== null" style="display: none">
                <line :x1="i === null ? 0 : pts[i].x * {{ $W }} / 100" :x2="i === null ? 0 : pts[i].x * {{ $W }} / 100" y1="{{ $padT }}" y2="{{ $padT + $plotH }}" stroke="#AEB8CC" stroke-width="1"/>
                <circle :cx="i === null ? 0 : pts[i].x * {{ $W }} / 100" :cy="i === null ? 0 : pts[i].y * {{ $H }} / 100" cx="0" cy="0" r="5" fill="{{ $color }}" stroke="#fff" stroke-width="2"/>
            </g>
            @foreach($values as $i => $v)
                <rect x="{{ $x($i) - $plotW / max(1, $n - 1) / 2 }}" y="{{ $padT }}" width="{{ $plotW / max(1, $n - 1) }}" height="{{ $plotH }}" fill="transparent" @mouseenter="i = {{ $i }}" @focus="i = {{ $i }}" tabindex="-1"/>
            @endforeach
        </svg>
        <div x-show="i !== null" x-cloak class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-navy-900 px-2.5 py-1.5 text-xs text-white shadow-lift" :style="i !== null && `left:${pts[i].x}%;top:calc(${pts[i].y}% - 10px)`">
            <span class="block text-white/60" x-text="pts[i]?.l"></span>
            <span class="font-semibold" x-text="pts[i]?.v + ' {{ $valueLabel }}'"></span>
        </div>
    </div>
    <table class="sr-only">
        <caption>{{ $title }}</caption>
        <tbody>@foreach($labels as $i => $label)<tr><th scope="row">{{ $label }}</th><td>{{ $values[$i] ?? 0 }}</td></tr>@endforeach</tbody>
    </table>
</figure>
