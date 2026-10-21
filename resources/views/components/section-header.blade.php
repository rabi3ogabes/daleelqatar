@props(['eyebrow' => null, 'title', 'subtitle' => null, 'href' => null, 'linkLabel' => null, 'center' => false])
<div {{ $attributes->class(['mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', 'items-center text-center sm:flex-col sm:items-center' => $center]) }}>
    <div @class(['max-w-2xl', 'mx-auto' => $center])>
        @if($eyebrow)<p class="eyebrow mb-2">{{ $eyebrow }}</p>@endif
        <h2 class="section-title">{{ $title }}</h2>
        <div @class(['divider-gold mt-3', 'mx-auto' => $center])></div>
        @if($subtitle)<p class="section-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if($href)
        <a href="{{ $href }}" class="group inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-gold-700 hover:text-gold-800">
            {{ $linkLabel ?? __('common.view_all') }}
            <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
        </a>
    @endif
</div>
