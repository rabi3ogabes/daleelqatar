@props(['code', 'icon' => 'compass', 'minimal' => false])
@php
    $known = \Illuminate\Support\Facades\Lang::has('errors.'.$code.'_title');
    $title = $known ? __('errors.'.$code.'_title') : __('errors.generic_title');
    $text = $known ? __('errors.'.$code.'_text') : __('errors.generic_text');
@endphp
@if($minimal)
    {{-- Self-contained: no database access, safe when the application is failing --}}
    <!DOCTYPE html>
    <html lang="{{ app()->getLocale() }}" dir="{{ locale_is_rtl() ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
        <title>{{ $title }} — {{ __('brand.name') }}</title>
        <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-navy-950 p-6 text-center text-white">
        <div class="pattern-lattice fixed inset-0" aria-hidden="true"></div>
        <main class="relative max-w-md">
            <img src="{{ asset('brand/daleel-qatar-logo-stacked-dark.svg') }}" alt="{{ __('brand.name') }}" class="mx-auto h-36">
            <p class="mt-8 text-6xl font-bold text-gold-400">{{ $code }}</p>
            <h1 class="mt-3 text-2xl font-bold !text-white">{{ $title }}</h1>
            <p class="mt-3 text-white/70">{{ $text }}</p>
            <a href="{{ url(app()->getLocale() === 'en' ? '/en' : '/') }}" class="btn btn-gold mt-8">{{ __('errors.go_home') }}</a>
        </main>
    </body>
    </html>
@else
    <x-layouts.app>
        @php seo()->title($title)->noindex(); @endphp
        <section class="container-app flex min-h-[60vh] flex-col items-center justify-center py-20 text-center">
            <div class="relative">
                <span class="text-[7rem] leading-none font-bold text-navy-100 sm:text-[9rem]">{{ $code }}</span>
                <span class="absolute inset-0 flex items-center justify-center"><span class="flex size-16 items-center justify-center rounded-2xl bg-navy-900 text-gold-300 shadow-lift"><x-icon :name="$icon" class="size-8" :stroke="1.5" /></span></span>
            </div>
            <h1 class="mt-6 text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
            <p class="mt-3 max-w-md text-muted-strong">{{ $text }}</p>
            @if($code == 404)
                <form action="{{ route('search') }}" method="GET" role="search" class="mt-8 flex w-full max-w-md gap-2">
                    <label for="err-q" class="sr-only">{{ __('errors.search_directory') }}</label>
                    <input id="err-q" name="q" type="search" class="input" placeholder="{{ __('home.search_placeholder') }}">
                    <button class="btn btn-primary"><x-icon name="search" class="size-4" /><span class="sr-only">{{ __('common.search') }}</span></button>
                </form>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('home') }}" class="btn btn-gold">{{ __('errors.go_home') }}</a>
                @if($code == 401 || $code == 403)@guest<a href="{{ route('login') }}" class="btn btn-outline">{{ __('nav.login') }}</a>@endguest @endif
                @if($code == 404)<a href="{{ route('categories.index') }}" class="btn btn-outline">{{ __('search.browse_categories') }}</a>@endif
                @if(! in_array((int) $code, [401, 403, 404], true) && url()->previous() !== url()->current())<a href="{{ url()->previous() }}" class="btn btn-outline"><x-icon name="arrow-left" class="size-4" />{{ __('errors.go_back') }}</a>@endif
            </div>
        </section>
    </x-layouts.app>
@endif
