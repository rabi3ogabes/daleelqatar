{{-- A week of opening hours, today highlighted. Expects $byDay (rows grouped by day_of_week) and $today. --}}
<dl class="mt-4 space-y-1 text-sm">
    @foreach([6, 0, 1, 2, 3, 4, 5] as $day)
        @php $rows = $byDay->get($day, collect()); @endphp
        <div @class(['flex justify-between gap-3 rounded-lg px-2.5 py-1.5', 'bg-gold-50 font-semibold text-navy-900' => $day === $today])>
            <dt>{{ __('business.days.'.$day) }} @if($day === $today)<span class="text-xs font-normal text-gold-700">· {{ __('business.today') }}</span>@endif</dt>
            <dd class="text-end">
                @if($rows->isEmpty())<span class="text-muted">—</span>
                @else{{ $rows->map->formatted()->implode(', ') }}@endif
            </dd>
        </div>
    @endforeach
</dl>
