@props(['variant' => 'light', 'size' => 'md', 'compact' => false, 'href' => null])
@php
    $dark = $variant === 'dark';
    $mark = ['sm' => 'size-9', 'md' => 'size-11', 'lg' => 'size-14'][$size] ?? 'size-11';
    $ar = ['sm' => 'text-lg', 'md' => 'text-xl', 'lg' => 'text-2xl'][$size] ?? 'text-xl';
    $tag = $href ? 'a' : 'span';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class('inline-flex items-center gap-2.5 rounded-lg') }} aria-label="{{ __('brand.name') }} — {{ __('nav.home') }}">
    {!! \App\Support\Brand::mark($variant, $mark.' shrink-0') !!}
    @unless($compact)
        <span class="flex flex-col leading-none">
            <span lang="ar" class="font-arabic {{ $ar }} font-bold {{ $dark ? 'text-ivory' : 'text-navy-900' }}" style="font-family: var(--font-arabic)">دليل قطر</span>
            <span lang="en" class="mt-1 font-sans text-[0.58rem] font-semibold tracking-[0.32em] {{ $dark ? 'text-gold-300' : 'text-navy-700' }}">DALEELQATAR</span>
        </span>
    @endunless
</{{ $tag }}>
