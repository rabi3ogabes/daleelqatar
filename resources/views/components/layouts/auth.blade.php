@props(['title', 'subtitle' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @php seo()->noindex(); @endphp
    @include('partials.head')
</head>
<body class="min-h-screen bg-ivory">
    @php $other = app()->getLocale() === 'ar' ? 'en' : 'ar'; @endphp
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
        {{-- Brand panel --}}
        <aside class="relative hidden overflow-hidden bg-navy-950 lg:block" aria-hidden="true">
            <img src="{{ asset('images/doha-skyline.svg') }}" alt="" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-navy-950/70 via-navy-950/30 to-navy-950/90"></div>
            <div class="pattern-lattice absolute inset-0 opacity-40"></div>
            <div class="relative flex h-full flex-col justify-between p-12 xl:p-16">
                <x-logo variant="dark" size="lg" :href="route('home')" />
                <div class="max-w-md">
                    <p class="text-3xl leading-snug font-bold text-white xl:text-4xl">{{ __('auth_ui.panel_title') }}</p>
                    <p class="mt-4 text-white/70">{{ __('auth_ui.panel_text') }}</p>
                    <ul class="mt-8 space-y-3">
                        @foreach(['panel_point_1', 'panel_point_2', 'panel_point_3'] as $point)
                            <li class="flex items-center gap-3 text-sm text-white/85">
                                <span class="flex size-6 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-icon name="check" class="size-3.5" :stroke="2.5" /></span>{{ __('auth_ui.'.$point) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                <p class="text-xs text-white/40">{{ __('footer.rights', ['year' => now()->year]) }}</p>
            </div>
        </aside>

        {{-- Form panel --}}
        <main id="main" class="flex min-h-screen flex-col">
            <div class="flex items-center justify-between px-5 py-5 sm:px-10">
                <x-logo size="sm" :href="route('home')" class="lg:invisible" />
                <div class="flex items-center gap-1">
                    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm"><x-icon name="house" class="size-4" /><span class="hidden sm:inline">{{ __('nav.home') }}</span></a>
                    <a href="{{ localized_url($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" class="btn btn-ghost btn-sm"><x-icon name="languages" class="size-4 text-gold-600" />{{ __('nav.switch_language') }}</a>
                </div>
            </div>
            <div class="flex flex-1 items-center justify-center px-5 pb-12 sm:px-10">
                <div class="w-full max-w-md animate-fade-up">
                    <div class="mb-8">
                        <span class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-navy-900 text-gold-300 shadow-soft lg:hidden">{!! \App\Support\Brand::mark('dark', 'size-8') !!}</span>
                        <h1 class="text-2xl font-bold text-navy-900 sm:text-3xl">{{ $title }}</h1>
                        @if($subtitle)<p class="mt-2 text-sm leading-7 text-muted-strong">{{ $subtitle }}</p>@endif
                    </div>
                    <x-flash />
                    {{ $slot }}
                    <p class="mt-10 flex items-center justify-center gap-1.5 text-xs text-muted"><x-icon name="lock" class="size-3.5" />{{ __('auth_ui.secure_note') }}</p>
                </div>
            </div>
        </main>
    </div>
    @include('partials.ui')
</body>
</html>
