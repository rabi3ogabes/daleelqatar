@props(['items' => [], 'dark' => false])
{{-- $items: [['label' => '…', 'url' => '…'|null], …] — Home is prepended automatically. --}}
@php
    $trail = array_merge([['label' => __('common.home'), 'url' => route('home')]], $items);
    seo()->jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($trail)->values()->map(fn ($c, $i) => array_filter([
            '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['label'], 'item' => $c['url'] ?? url()->current(),
        ]))->all(),
    ]);
@endphp
<nav aria-label="{{ __('nav.breadcrumb') }}" {{ $attributes->class('text-sm') }}>
    <ol class="flex flex-wrap items-center gap-1.5 {{ $dark ? 'text-white/70' : 'text-muted-strong' }}">
        @foreach($trail as $crumb)
            <li class="flex items-center gap-1.5">
                @if(! $loop->first)
                    <x-icon name="chevron-right" class="size-3.5 opacity-60" />
                @endif
                @if(! $loop->last && ! empty($crumb['url']))
                    <a href="{{ $crumb['url'] }}" class="transition {{ $dark ? 'hover:text-gold-300' : 'hover:text-gold-700' }}">{{ $crumb['label'] }}</a>
                @else
                    <span aria-current="page" class="font-medium {{ $dark ? 'text-white' : 'text-navy-900' }}">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
