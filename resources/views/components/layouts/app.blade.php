@props(['bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_is_rtl() ? 'rtl' : 'ltr' }}" data-confirm-title="{{ __('common.confirm_title') }}" data-confirm-label="{{ __('common.confirm_action') }}" data-copied="{{ __('directory.copied') }}" data-reveal-limit="{{ __('directory.reveal_limit') }}">
<head>
    @include('partials.head')
    @include('partials.integrations')
</head>
<body class="min-h-screen {{ $bodyClass }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[90] focus:rounded-lg focus:bg-navy-900 focus:px-4 focus:py-2 focus:text-white">{{ __('nav.skip_to_content') }}</a>
    @include('partials.header')
    <main id="main" tabindex="-1" class="outline-none">
        {{ $slot }}
    </main>
    @include('partials.footer')
    @include('partials.ui')
    @if(\App\Support\Integrations::consentBanner())
        @include('partials.consent')
    @endif
    @stack('scripts')
</body>
</html>
