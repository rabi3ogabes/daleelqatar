@php
    $altLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $heroTitle = setting_t('home.hero_title', __('home.meta_title'));
    $heroAlt = (setting('home.hero_title') ?? [])[$altLocale] ?? null;
@endphp
<x-layouts.app>
    {{-- ================================================================ HERO --}}
    <section class="relative isolate overflow-hidden bg-navy-950">
        <img src="{{ $heroImage }}" alt="" fetchpriority="high" class="absolute inset-0 -z-10 size-full object-cover object-center" aria-hidden="true">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/55 via-navy-950/35 to-navy-950/80" aria-hidden="true"></div>
        <div class="absolute inset-x-0 bottom-0 -z-10 h-40 bg-gradient-to-t from-ivory/0 to-transparent" aria-hidden="true"></div>

        <div class="container-app flex min-h-[34rem] flex-col items-center justify-center py-20 text-center sm:min-h-[38rem] lg:py-28">
            <p class="animate-fade-up mb-5 inline-flex items-center gap-2 rounded-full border border-gold-300/30 bg-navy-950/30 px-4 py-1.5 text-xs font-medium text-gold-200 backdrop-blur">
                <x-icon name="sparkles" class="size-3.5" />{{ setting_t('brand.slogan', __('brand.slogan')) }}
            </p>
            <h1 class="animate-fade-up max-w-4xl text-3xl font-bold text-white sm:text-5xl lg:text-[3.4rem] lg:leading-[1.25]" style="animation-delay:.05s">{{ $heroTitle }}</h1>
            @if($heroAlt)
                <p lang="{{ $altLocale }}" dir="{{ $altLocale === 'ar' ? 'rtl' : 'ltr' }}" class="animate-fade-up mt-4 text-base text-white/80 sm:text-xl" style="animation-delay:.1s">{{ $heroAlt }}</p>
            @endif
            <p class="animate-fade-up mt-3 max-w-2xl text-sm text-white/65 sm:text-base" style="animation-delay:.12s">{{ setting_t('home.hero_subtitle', __('brand.subtitle')) }}</p>

            <div class="animate-fade-up mt-9 w-full max-w-4xl" style="animation-delay:.18s">
                <x-search-form :categories="$allCategories" :municipalities="$allMunicipalities" />
            </div>

            <nav aria-label="{{ __('home.quick_categories') }}" class="animate-fade-up mt-6 flex flex-wrap justify-center gap-2" style="animation-delay:.24s">
                @foreach($categories->take(6) as $category)
                    <x-category-tile :category="$category" variant="chip" />
                @endforeach
            </nav>
        </div>

        <div class="relative border-t border-white/10 bg-navy-950/60 backdrop-blur">
            <dl class="container-app grid grid-cols-3 divide-x divide-white/10 py-4 text-center rtl:divide-x-reverse">
                @foreach([['businesses', 'store'], ['categories', 'layout-grid'], ['areas', 'map-pin']] as [$key, $icon])
                    <div class="flex flex-col items-center gap-1 sm:flex-row sm:justify-center sm:gap-3">
                        <x-icon :name="$icon" class="hidden size-5 text-gold-400 sm:block" />
                        <dt class="order-2 text-xs text-white/60 sm:order-none">{{ __('home.stats_'.$key) }}</dt>
                        <dd class="text-xl font-bold text-white">{{ format_number($stats[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ========================================================== CATEGORIES --}}
    <section class="container-app py-16 sm:py-20" aria-labelledby="cat-title">
        <x-section-header :eyebrow="__('home.categories_eyebrow')" :title="__('home.categories_title')" :subtitle="__('home.categories_subtitle')" :href="route('categories.index')" id="cat-title" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
            @foreach($categories->take(11) as $category)
                <x-category-tile :category="$category" :count="$categoryCounts[$category->id] ?? 0" />
            @endforeach
            <a href="{{ route('categories.index') }}" class="group card card-hover flex flex-col items-center justify-center gap-3 border-dashed px-3 py-6 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-navy-900 text-gold-300 transition group-hover:bg-gold-400 group-hover:text-navy-950"><x-icon name="layout-grid" class="size-7" :stroke="1.5" /></span>
                <span class="text-sm font-semibold text-navy-900">{{ __('home.more_categories') }}</span>
                <span class="text-xs text-muted-strong">{{ __('common.view_all') }}</span>
            </a>
        </div>
    </section>

    {{-- ============================================================ FEATURED --}}
    <section class="bg-white py-16 sm:py-20" aria-labelledby="featured-title">
        <div class="container-app">
            <x-section-header :eyebrow="__('home.featured_eyebrow')" :title="__('home.featured_title')" :subtitle="__('home.featured_subtitle')" :href="route('businesses.featured')" id="featured-title" />
            @if($featured->isNotEmpty())
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($featured as $business)
                        <x-business-card :business="$business" :favorited="in_array($business->id, $favoriteIds, true)" />
                    @endforeach
                </div>
            @else
                <x-empty-state icon="sparkles" :title="__('home.empty_featured')" />
            @endif
        </div>
    </section>

    {{-- =========================================================== LOCATIONS --}}
    <section class="container-app py-16 sm:py-20" aria-labelledby="loc-title">
        <x-section-header :eyebrow="__('home.locations_eyebrow')" :title="__('home.locations_title')" :subtitle="__('home.locations_subtitle')" :href="route('locations.index')" id="loc-title" />
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach($featuredAreas->take(1) as $area)
                <a href="{{ route('search', ['municipality' => $area->municipality->slug, 'area' => $area->slug]) }}" class="group relative col-span-2 row-span-2 flex min-h-56 flex-col justify-end overflow-hidden rounded-2xl bg-navy-900 p-6 text-white shadow-soft md:col-span-1 lg:col-span-2">
                    <img src="{{ asset('images/doha-skyline.svg') }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover opacity-80 transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/40 to-transparent"></span>
                    <span class="relative">
                        <span class="badge bg-gold-400 text-navy-950"><x-icon name="sparkles" class="size-3" />{{ $area->municipality->t('name') }}</span>
                        <span class="mt-3 block text-3xl font-bold">{{ $area->t('name') }}</span>
                        <span class="mt-1 block text-sm text-white/70">{{ trans_choice('home.businesses_count', $area->businesses_count, ['count' => $area->businesses_count]) }}</span>
                    </span>
                </a>
            @endforeach
            @foreach($municipalities->take(8) as $m)
                <a href="{{ route('locations.show', $m) }}" class="group card card-hover pattern-lattice relative flex min-h-28 flex-col justify-between overflow-hidden !bg-navy-900 p-5 text-white">
                    <x-icon name="map-pin" class="size-5 text-gold-400" />
                    <span>
                        <span class="block text-lg font-semibold text-white">{{ $m->t('name') }}</span>
                        <span class="text-xs text-white/60">{{ trans_choice('home.businesses_count', $locationCounts[$m->id] ?? 0, ['count' => $locationCounts[$m->id] ?? 0]) }}</span>
                    </span>
                    <x-icon name="arrow-up-right" class="absolute end-4 top-4 size-4 text-white/30 transition group-hover:text-gold-300" />
                </a>
            @endforeach
        </div>
    </section>

    {{-- ====================================================== LATEST + POPULAR --}}
    <section class="bg-white py-16 sm:py-20" aria-labelledby="latest-title">
        <div class="container-app grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <x-section-header :eyebrow="__('home.latest_eyebrow')" :title="__('home.latest_title')" :href="route('businesses.latest')" id="latest-title" />
                @if($latest->isNotEmpty())
                    <div class="grid gap-4">
                        @foreach($latest->take(5) as $business)
                            <x-business-card :business="$business" layout="list" />
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="store" :title="__('home.empty_latest')">
                        <a href="{{ route('add-business') }}" class="btn btn-primary">{{ __('nav.add_business') }}</a>
                    </x-empty-state>
                @endif
            </div>
            <aside class="lg:col-span-4" aria-labelledby="popular-title">
                <div class="sticky top-24 rounded-2xl bg-navy-900 p-6 text-white shadow-lift">
                    <p class="eyebrow !text-gold-300">{{ __('home.popular_title') }}</p>
                    <h2 id="popular-title" class="mt-2 text-xl font-bold !text-white">{{ __('home.popular_title') }}</h2>
                    <p class="mt-1 text-sm text-white/60">{{ __('home.popular_subtitle') }}</p>
                    <ol class="mt-6 space-y-3">
                        @forelse($popular as $i => $business)
                            <li>
                                <a href="{{ route('business.show', $business) }}" class="group flex items-center gap-3 rounded-xl p-2 transition hover:bg-white/5">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-sm font-bold text-gold-300 ring-1 ring-white/10">{{ $i + 1 }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1 truncate text-sm font-semibold text-white group-hover:text-gold-200">{{ $business->t('name') }}@if($business->is_verified)<x-icon name="badge-check" class="size-4 text-gold-400" />@endif</span>
                                        <span class="block truncate text-xs text-white/55">{{ $business->category?->t('name') }} · {{ $business->locationLabel() }}</span>
                                    </span>
                                    <x-icon name="chevron-right" class="size-4 text-white/30" />
                                </a>
                            </li>
                        @empty
                            <li class="text-sm text-white/60">{{ __('home.empty_latest') }}</li>
                        @endforelse
                    </ol>
                    <a href="{{ route('search', ['sort' => 'popular']) }}" class="btn btn-outline-light mt-6 w-full">{{ __('common.view_all') }}</a>
                </div>
            </aside>
        </div>
    </section>

    {{-- ============================================================== OFFERS --}}
    @if($offers->isNotEmpty())
        <section class="container-app py-16 sm:py-20" aria-labelledby="offers-title">
            <x-section-header :eyebrow="__('home.offers_eyebrow')" :title="__('home.offers_title')" :subtitle="__('home.offers_subtitle')" :href="route('offers.index')" id="offers-title" />
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($offers as $offer)
                    <x-offer-card :offer="$offer" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============================================================= BANNERS --}}
    @foreach($banners as $banner)
        <section class="container-app pb-6">
            <a href="{{ safe_external_url($banner->link_url) ?? (str_starts_with((string) $banner->link_url, '/') ? url($banner->link_url) : '#') }}" class="group relative flex min-h-40 items-center overflow-hidden rounded-2xl bg-navy-900 p-8 text-white">
                @if($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover opacity-50 transition duration-700 group-hover:scale-105">@endif
                <span class="relative max-w-xl">
                    <span class="block text-2xl font-bold">{{ $banner->t('title') }}</span>
                    @if($banner->t('subtitle'))<span class="mt-2 block text-white/75">{{ $banner->t('subtitle') }}</span>@endif
                    @if($banner->t('cta_label'))<span class="btn btn-gold mt-5">{{ $banner->t('cta_label') }}</span>@endif
                </span>
            </a>
        </section>
    @endforeach

    {{-- ================================================================= CTA --}}
    <section class="container-app py-10 sm:py-16" aria-labelledby="cta-title">
        <div class="relative overflow-hidden rounded-[2rem] bg-navy-900 px-6 py-12 sm:px-12 lg:py-16">
            <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
            <div class="absolute -end-24 -top-24 size-80 rounded-full bg-gold-400/15 blur-3xl" aria-hidden="true"></div>
            <div class="relative grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <p class="eyebrow !text-gold-300">{{ __('home.cta_eyebrow') }}</p>
                    <h2 id="cta-title" class="mt-3 text-3xl font-bold !text-white sm:text-4xl">{{ __('home.cta_title') }}</h2>
                    <p class="mt-4 max-w-lg text-white/70">{{ __('home.cta_text') }}</p>
                    <ul class="mt-6 space-y-2.5">
                        @foreach(['cta_point_1', 'cta_point_2', 'cta_point_3'] as $point)
                            <li class="flex items-center gap-2.5 text-sm text-white/85"><span class="flex size-5 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-icon name="check" class="size-3.5" :stroke="2.5" /></span>{{ __('home.'.$point) }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('add-business') }}" class="btn btn-gold btn-lg">{{ __('home.cta_primary') }}<x-icon name="arrow-right" class="size-5" /></a>
                        <a href="{{ route('faq') }}" class="btn btn-outline-light btn-lg">{{ __('home.cta_secondary') }}</a>
                    </div>
                </div>
                {{-- Stylised dashboard preview --}}
                <div class="relative hidden lg:block" aria-hidden="true">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-2xl backdrop-blur">
                        <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                            <span class="size-2.5 rounded-full bg-white/20"></span><span class="size-2.5 rounded-full bg-white/20"></span><span class="size-2.5 rounded-full bg-gold-400/80"></span>
                            <span class="ms-3 text-xs text-white/50">{{ __('nav.dashboard') }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            @foreach(['eye', 'phone', 'star'] as $icon)
                                <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10"><x-icon :name="$icon" class="size-4 text-gold-300" /><div class="mt-3 h-3 w-1/2 rounded bg-white/20"></div><div class="mt-2 h-1.5 w-2/3 rounded bg-white/10"></div></div>
                            @endforeach
                        </div>
                        <svg viewBox="0 0 300 90" class="mt-4 w-full" fill="none"><path d="M0 70 C 40 60, 60 72, 90 55 S 150 40, 180 45 S 240 20, 300 12" stroke="#D7B875" stroke-width="2.5"/><path d="M0 70 C 40 60, 60 72, 90 55 S 150 40, 180 45 S 240 20, 300 12 V90 H0Z" fill="url(#g1)" opacity=".25"/><defs><linearGradient id="g1" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#D7B875"/><stop offset="1" stop-color="#D7B875" stop-opacity="0"/></linearGradient></defs></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- =============================================================== TRUST --}}
    @if(setting('home.show_trust', true))
        <section class="container-app py-16 sm:py-20" aria-labelledby="trust-title">
            <x-section-header :eyebrow="__('home.trust_eyebrow')" :title="__('home.trust_title')" center id="trust-title" />
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([['badge-check', 1], ['shield-check', 2], ['languages', 3], ['star', 4]] as [$icon, $n])
                    <div class="card card-pad">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon :name="$icon" class="size-6" :stroke="1.5" /></span>
                        <h3 class="mt-5 font-semibold text-navy-900">{{ __('home.trust_'.$n.'_title') }}</h3>
                        <p class="mt-2 text-sm leading-7 text-muted-strong">{{ __('home.trust_'.$n.'_text') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
