<nav class="mb-4 flex gap-1 overflow-x-auto scrollbar-none" aria-label="{{ __('common.status') }}">
    @foreach(\App\Enums\RequestStatus::cases() as $s)
        <a href="{{ route($route, ['status' => $s->value]) }}" @class(['btn btn-sm', 'btn-primary' => $status === $s, 'btn-ghost' => $status !== $s])>{{ $s->label() }}</a>
    @endforeach
    <a href="{{ route($route, ['status' => 'all']) }}" @class(['btn btn-sm', 'btn-primary' => $status === null, 'btn-ghost' => $status !== null])>{{ __('common.all') }}</a>
</nav>
