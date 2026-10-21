@php
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $ga4 = \App\Support\Integrations::ga4();
    $ads = \App\Support\Integrations::showAds();
@endphp
@foreach(\App\Support\Integrations::verificationMeta() as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@if($ga4 || $ads)
<script nonce="{{ $nonce }}">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', region: @json(\App\Support\Integrations::CONSENT_REGIONS), wait_for_update: 500});
gtag('consent', 'default', {ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted'});
try { var dqc = localStorage.getItem('dq-consent'); if (dqc) { var g = dqc === 'granted' ? 'granted' : 'denied'; gtag('consent', 'update', {ad_storage: g, ad_user_data: g, ad_personalization: g, analytics_storage: g}); } } catch (e) {}
@if($ga4)
gtag('js', new Date());
gtag('config', @json($ga4), {anonymize_ip: true});
@endif
</script>
@endif
@if($ga4)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}" nonce="{{ $nonce }}"></script>
@endif
@if($ads)
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ \App\Support\Integrations::adsenseClient() }}" crossorigin="anonymous" nonce="{{ $nonce }}"></script>
@endif
