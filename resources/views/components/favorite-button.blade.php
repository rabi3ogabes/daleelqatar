@props(['business', 'favorited' => false, 'labelled' => false])
@php
    $canFavorite = auth()->check() && auth()->user()->hasVerifiedEmail();
    $url = $canFavorite ? route('business.favorite', $business) : null;
@endphp
<button type="button"
    x-data="favorite(@js($url), @js((bool) $favorited), @js(route('login')))"
    @click.prevent.stop="toggle()"
    :aria-pressed="on.toString()"
    aria-label="{{ __('business.save') }}: {{ $business->t('name') }}"
    {{ $attributes->class($labelled ? 'btn btn-outline' : 'flex size-9 items-center justify-center rounded-full bg-white/90 text-navy-900 shadow-soft backdrop-blur transition hover:scale-105 hover:bg-white') }}>
    <svg viewBox="0 0 24 24" class="size-[1.15rem] transition" :class="on ? 'fill-gold-500 stroke-gold-600' : 'fill-transparent stroke-current'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
    @if($labelled)<span x-text="on ? @js(__('business.saved')) : @js(__('business.save'))">{{ $favorited ? __('business.saved') : __('business.save') }}</span>@endif
</button>
