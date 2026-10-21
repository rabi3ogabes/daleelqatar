@props(['action'])
{{-- Filter bar for index pages (GET form keeps URLs shareable). --}}
<form method="GET" action="{{ $action }}" {{ $attributes->class('card mb-5 flex flex-wrap items-end gap-3 p-4') }}>
    {{ $slot }}
    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary" data-no-busy><x-icon name="filter" class="size-4" />{{ __('common.apply') }}</button>
        @if(request()->query())
            <a href="{{ $action }}" class="btn btn-ghost">{{ __('common.reset') }}</a>
        @endif
    </div>
</form>
