@props(['category', 'count' => null, 'variant' => 'tile'])
@php $url = route('categories.show', $category); @endphp
@if($variant === 'chip')
    <a href="{{ $url }}" {{ $attributes->class('inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-medium text-white backdrop-blur transition hover:border-gold-300/60 hover:bg-white/20') }}>
        <x-icon :name="$category->icon ?? 'layout-grid'" class="size-4 text-gold-300" />{{ $category->t('name') }}
    </a>
@else
    <a href="{{ $url }}" {{ $attributes->class('group card card-hover flex flex-col items-center justify-center gap-3 px-3 py-6 text-center') }}>
        <span class="flex size-14 items-center justify-center rounded-2xl bg-gold-50 text-gold-700 ring-1 ring-gold-100 transition duration-300 group-hover:bg-navy-900 group-hover:text-gold-300 group-hover:ring-navy-900">
            <x-icon :name="$category->icon ?? 'layout-grid'" class="size-7" :stroke="1.5" />
        </span>
        <span>
            <span class="block text-sm font-semibold text-navy-900">{{ $category->t('name') }}</span>
            <span class="mt-0.5 block text-[0.7rem] text-muted" lang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ $category->t('name', app()->getLocale() === 'ar' ? 'en' : 'ar') }}</span>
        </span>
        @if($count !== null)<span class="text-xs text-muted-strong">{{ trans_choice('home.businesses_count', $count, ['count' => $count]) }}</span>@endif
    </a>
@endif
