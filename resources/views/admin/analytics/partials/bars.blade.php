{{-- Ranked list with proportional bars (one hue: these are magnitudes). $rows: [['label','value','lead'?,'sub'?]] --}}
@php $max = max(1, collect($rows)->max('value') ?? 1); $sum = max(1, collect($rows)->sum('value')); @endphp
@if(collect($rows)->isEmpty())
    <p class="px-5 py-8 text-center text-sm text-muted-strong">{{ __('analytics.no_data') }}</p>
@else
    <ul class="space-y-1 px-3 py-3">
        @foreach($rows as $row)
            <li class="group relative overflow-hidden rounded-lg px-3 py-2 text-sm" title="{{ format_number($row['value']) }} · {{ round(100 * $row['value'] / $sum) }}%">
                <span class="absolute inset-y-1 start-0 rounded-md bg-gold-100/70 transition-all group-hover:bg-gold-200/70" style="width: {{ max(2, round(100 * $row['value'] / $max)) }}%" aria-hidden="true"></span>
                <span class="relative flex items-center gap-2.5">
                    @if(! empty($row['lead']))<span class="shrink-0 text-base leading-none">{{ $row['lead'] }}</span>@endif
                    <span class="min-w-0 flex-1 truncate font-medium text-navy-900" @if(! empty($row['ltr'])) dir="ltr" @endif>{{ $row['label'] }}@if(! empty($row['sub']))<span class="ms-1.5 text-xs font-normal text-muted">{{ $row['sub'] }}</span>@endif</span>
                    <span class="shrink-0 tabular-nums font-semibold text-navy-900">{{ format_number($row['value']) }}</span>
                    <span class="w-10 shrink-0 text-end text-xs tabular-nums text-muted-strong">{{ round(100 * $row['value'] / $sum) }}%</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
