@php
    $seo = seo();
    $title = $seo->fullTitle();
    $description = $seo->metaDescription();
    $canonical = $seo->canonical ?? url()->current().(request()->integer('page') > 1 ? '?page='.request()->integer('page') : '');
    $image = $seo->image ?? asset('brand/og-image.jpg');
    $alternates = $seo->alternates ?? ['ar' => localized_url('ar'), 'en' => localized_url('en')];
    $locale = app()->getLocale();
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $seo->shouldIndex() ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ $canonical }}">
@if(! $seo->noindex)
    @foreach($alternates as $lang => $href)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates['ar'] ?? $canonical }}">
@endif
<meta property="og:site_name" content="{{ __('brand.name') }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_QA' : 'en_US' }}">
<meta property="og:locale:alternate" content="{{ $locale === 'ar' ? 'en_US' : 'ar_QA' }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
@foreach($seo->jsonLd as $schema)
    <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
