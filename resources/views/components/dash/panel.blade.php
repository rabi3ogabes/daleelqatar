@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->class('card overflow-hidden') }}>
    @if($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-navy-50 px-5 py-4">
            <div class="min-w-0">
                @if($title)<h2 class="text-base font-semibold text-navy-900">{{ $title }}</h2>@endif
                @if($subtitle)<p class="mt-0.5 text-xs text-muted-strong">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
</section>
