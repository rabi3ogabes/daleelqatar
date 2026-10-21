{{-- Status tabs for moderation queues. Expects: $route, $cases (enum cases), $current (string), $counts (collection) --}}
<nav class="mb-4 flex gap-1 overflow-x-auto scrollbar-none" aria-label="{{ __('common.status') }}">
    @foreach($cases as $case)
        <a href="{{ route($route, array_merge(request()->except(['status', 'page']), ['status' => $case->value])) }}" @class(['btn btn-sm', 'btn-primary' => $current === $case->value, 'btn-ghost' => $current !== $case->value])>
            {{ $case->label() }} <span class="opacity-60">{{ format_number($counts[$case->value] ?? 0) }}</span>
        </a>
    @endforeach
    <a href="{{ route($route, array_merge(request()->except(['status', 'page']), ['status' => 'all'])) }}" @class(['btn btn-sm', 'btn-primary' => $current === 'all', 'btn-ghost' => $current !== 'all'])>{{ __('common.all') }}</a>
</nav>
