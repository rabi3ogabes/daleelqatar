@props(['labels' => [], 'series' => [], 'title', 'height' => 220])
{{-- Grouped vertical bars. $series: [['label' => …, 'values' => [...], 'color' => '#hex']]. --}}
@php
    $n = count($labels); $k = max(1, count($series));
    $W = 640; $H = (int) $height; $padL = 40; $padR = 8; $padT = 12; $padB = 28;
    $all = collect($series)->flatMap(fn ($s) => $s['values']);
    [$max, $step] = \App\Support\ChartPalette::niceScale((float) ($all->max() ?? 0));
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $slot = $n ? $plotW / $n : $plotW;
    $barW = min(18, ($slot * 0.7 - ($k - 1) * 2) / $k);
    $y = fn ($v) => $padT + $plotH - ($max > 0 ? $plotH * $v / $max : 0);
    $every = max(1, (int) ceil($n / 8));
@endphp
<figure {{ $attributes }} x-data="{ tip: null }">
    <figcaption class="sr-only">{{ $title }}</figcaption>
    <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted-strong">
        @foreach($series as $s)<span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span>@endforeach
    </div>
    <div class="relative" dir="ltr">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full" role="img" aria-label="{{ $title }}">
            @for($t = 0; $t <= $max + 0.0001; $t += $step)
                <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" stroke="#E9ECF2" @if($t > 0) stroke-dasharray="3 4" @endif/>
                <text x="{{ $padL - 8 }}" y="{{ $y($t) + 4 }}" text-anchor="end" font-size="11" fill="#5F6672">{{ format_number($t) }}</text>
            @endfor
            @foreach($labels as $i => $label)
                @php $groupX = $padL + $slot * $i + ($slot - ($barW * $k + 2 * ($k - 1))) / 2; @endphp
                @foreach($series as $j => $s)
                    @php $v = $s['values'][$i] ?? 0; $bx = $groupX + $j * ($barW + 2); $by = $y($v); $bh = max(0, $padT + $plotH - $by); @endphp
                    @if($bh > 0)
                        <path d="M{{ $bx }},{{ $padT + $plotH }} V{{ $by + min(4, $bh) }} Q{{ $bx }},{{ $by }} {{ $bx + min(4, $barW / 2) }},{{ $by }} H{{ $bx + $barW - min(4, $barW / 2) }} Q{{ $bx + $barW }},{{ $by }} {{ $bx + $barW }},{{ $by + min(4, $bh) }} V{{ $padT + $plotH }} Z" fill="{{ $s['color'] }}"/>
                    @endif
                    <rect x="{{ $bx - 1 }}" y="{{ $padT }}" width="{{ $barW + 2 }}" height="{{ $plotH }}" fill="transparent"
                        @mouseenter="tip = { x: {{ round(($bx + $barW / 2) / $W * 100, 2) }}, y: {{ round($by / $H * 100, 2) }}, t: @js($label.' · '.$s['label']), v: @js(format_number($v)) }" @mouseleave="tip = null"/>
                @endforeach
                @if($i % $every === 0)
                    <text x="{{ $padL + $slot * $i + $slot / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="11" fill="#5F6672">{{ $label }}</text>
                @endif
            @endforeach
        </svg>
        <div x-show="tip" x-cloak class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-navy-900 px-2.5 py-1.5 text-xs text-white shadow-lift" :style="tip && `left:${tip.x}%;top:calc(${tip.y}% - 8px)`">
            <span class="block text-white/60" x-text="tip?.t"></span><span class="font-semibold" x-text="tip?.v"></span>
        </div>
    </div>
    <table class="sr-only">
        <caption>{{ $title }}</caption>
        <thead><tr><th scope="col"></th>@foreach($series as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach</tr></thead>
        <tbody>@foreach($labels as $i => $label)<tr><th scope="row">{{ $label }}</th>@foreach($series as $s)<td>{{ $s['values'][$i] ?? 0 }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</figure>
