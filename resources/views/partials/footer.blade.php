@php
    $nav = app(\App\Support\NavigationData::class);
    $socials = collect(['instagram', 'x', 'facebook', 'linkedin', 'tiktok'])->mapWithKeys(fn ($k) => [$k => safe_external_url(setting('social.'.$k))])->filter();
@endphp
<footer class="relative overflow-hidden bg-navy-950 text-white/75">
    <div class="pattern-lattice pointer-events-none absolute inset-0 opacity-60" aria-hidden="true"></div>
    <div class="relative container-app py-14">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-logo variant="dark" size="lg" :href="route('home')" />
                <p class="mt-5 max-w-sm text-sm leading-7 text-white/65">{{ setting_t('footer.about', __('brand.subtitle')) }}</p>
                <p class="mt-4 text-sm font-semibold text-gold-300">{{ setting_t('brand.slogan', __('brand.slogan')) }}</p>
                @if($socials->isNotEmpty())
                    <div class="mt-6 flex items-center gap-2" aria-label="{{ __('footer.follow') }}">
                        @foreach($socials as $platform => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="flex size-10 items-center justify-center rounded-xl bg-white/5 text-white/80 ring-1 ring-white/10 transition hover:bg-gold-400 hover:text-navy-950">
                                <x-icon :name="$platform === 'x' ? 'brand-x' : $platform" class="size-4.5" /><span class="sr-only">{{ ucfirst($platform) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4 lg:col-span-8">
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ __('footer.explore') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li><a class="hover:text-gold-300" href="{{ route('search') }}">{{ __('nav.explore') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('categories.index') }}">{{ __('nav.categories') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('businesses.featured') }}">{{ __('nav.featured') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('businesses.latest') }}">{{ __('nav.latest') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('offers.index') }}">{{ __('nav.offers') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ __('footer.top_categories') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach($nav->footerCategories() as $category)
                            <li><a class="hover:text-gold-300" href="{{ route('categories.show', $category) }}">{{ $category->t('name') }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ __('footer.top_locations') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach($nav->footerLocations() as $municipality)
                            <li><a class="hover:text-gold-300" href="{{ route('locations.show', $municipality) }}">{{ $municipality->t('name') }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ __('footer.company') }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li><a class="hover:text-gold-300" href="{{ route('about') }}">{{ __('nav.about') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('contact') }}">{{ __('nav.contact') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('faq') }}">{{ __('nav.faq') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('add-business') }}">{{ __('nav.add_business') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('privacy') }}">{{ __('nav.privacy') }}</a></li>
                        <li><a class="hover:text-gold-300" href="{{ route('terms') }}">{{ __('nav.terms') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex-row">
            <p>{{ __('footer.rights', ['year' => now()->year]) }}</p>
            <p class="flex items-center gap-2"><span class="inline-block h-px w-6 bg-gold-500/60"></span>{{ __('footer.made_in') }}<span class="inline-block h-px w-6 bg-gold-500/60"></span></p>
            <p dir="ltr">daleelqatar.com</p>
        </div>
    </div>
</footer>
