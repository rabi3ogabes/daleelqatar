@props(['business', 'layout' => 'grid', 'favorited' => false, 'eager' => false])
@php
    $url = route('business.show', $business);
    $location = $business->locationLabel();
    $open = $business->relationLoaded('openingHours') ? $business->isOpenNow() : null;
@endphp
@if($layout === 'list')
<article {{ $attributes->class('group card card-hover relative flex gap-4 overflow-hidden p-3 sm:p-4') }}>
    <x-business-cover :business="$business" class="aspect-square w-28 shrink-0 rounded-xl sm:w-40" :eager="$eager" sizes="160px" />
    <div class="flex min-w-0 flex-1 flex-col py-1">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="flex items-center gap-1.5 text-base font-semibold text-navy-900">
                    <a href="{{ $url }}" class="truncate after:absolute after:inset-0 hover:text-gold-800">{{ $business->t('name') }}</a>
                    @if($business->is_verified)<x-icon name="badge-check" class="size-4.5 text-gold-600" title="{{ __('business.verified') }}" />@endif
                </h3>
                <p class="mt-0.5 text-sm text-gold-700">{{ $business->category?->t('name') }}</p>
            </div>
            @if($business->isFeaturedNow())<span class="badge badge-gold hidden sm:inline-flex"><x-icon name="sparkles" class="size-3" />{{ __('business.featured') }}</span>@endif
        </div>
        @if($business->t('tagline'))<p class="mt-2 line-clamp-2 text-sm text-muted-strong">{{ $business->t('tagline') }}</p>@endif
        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 pt-3 text-xs text-muted-strong">
            @if($location)<span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-3.5" />{{ $location }}</span>@endif
            <x-rating :value="$business->rating_avg" :count="$business->reviews_count" />
            @if($open !== null)<span class="inline-flex items-center gap-1 font-medium {{ $open ? 'text-success' : 'text-muted' }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $open ? __('business.open_now') : __('business.closed_now') }}</span>@endif
            @if($business->is_demo)<span class="badge badge-muted" title="{{ __('common.sample_listing_hint') }}">{{ __('common.sample_listing') }}</span>@endif
        </div>
    </div>
</article>
@else
<article {{ $attributes->class('group card card-hover relative flex flex-col overflow-hidden') }}>
    <div class="relative">
        <x-business-cover :business="$business" :eager="$eager" />
        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
            <div class="flex flex-wrap gap-1.5">
                @if($business->isFeaturedNow())<span class="badge bg-navy-950/75 text-gold-200 backdrop-blur"><x-icon name="sparkles" class="size-3" />{{ __('business.featured') }}</span>@endif
                @if($business->is_demo)<span class="badge bg-white/85 text-muted-strong backdrop-blur" title="{{ __('common.sample_listing_hint') }}">{{ __('common.sample_listing') }}</span>@endif
            </div>
            <span class="relative z-10">
                <x-favorite-button :business="$business" :favorited="$favorited" />
            </span>
        </div>
        @if($business->logo_path && $business->cardImageUrl())
            <img src="{{ $business->logoUrl() }}" alt="" loading="lazy" class="absolute -bottom-5 start-4 size-12 rounded-xl border-2 border-white bg-white object-contain shadow-soft">
        @endif
    </div>
    <div @class(['flex flex-1 flex-col p-4', 'pt-7' => $business->logo_path && $business->cardImageUrl()])>
        <p class="text-xs font-medium text-gold-700">{{ $business->category?->t('name') }}</p>
        <h3 class="mt-1 flex items-center gap-1.5 font-semibold text-navy-900">
            <a href="{{ $url }}" class="line-clamp-1 after:absolute after:inset-0 hover:text-gold-800">{{ $business->t('name') }}</a>
            @if($business->is_verified)<x-icon name="badge-check" class="size-4.5 text-gold-600" /><span class="sr-only">{{ __('business.verified') }}</span>@endif
        </h3>
        @if($location)
            <p class="mt-1.5 flex items-center gap-1 text-xs text-muted-strong"><x-icon name="map-pin" class="size-3.5" /><span class="line-clamp-1">{{ $location }}</span></p>
        @endif
        <div class="mt-auto flex items-center justify-between gap-2 pt-3">
            <x-rating :value="$business->rating_avg" :count="$business->reviews_count" />
            @if($open !== null)
                <span class="inline-flex items-center gap-1 text-xs font-medium {{ $open ? 'text-success' : 'text-muted' }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $open ? __('business.open_now') : __('business.closed_now') }}</span>
            @endif
        </div>
    </div>
</article>
@endif
