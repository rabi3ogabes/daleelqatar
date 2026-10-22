@props(['tab'])
<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-navy-100 scrollbar-none" aria-label="{{ __('analytics.title') }}">
    @foreach(['visitors' => 'users', 'directory' => 'store'] as $key => $icon)
        <a href="{{ route('admin.analytics.index', $key === 'directory' ? ['tab' => 'directory'] : []) }}" @class(['-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition', 'border-gold-500 text-navy-900' => $tab === $key, 'border-transparent text-muted-strong hover:text-navy-900' => $tab !== $key]) @if($tab === $key) aria-current="page" @endif>
            <x-icon :name="$icon" class="size-4" />{{ __('analytics.tabs.'.$key) }}
        </a>
    @endforeach
</nav>
