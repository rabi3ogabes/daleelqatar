@props(['url', 'title', 'text' => null, 'trackUrl' => null, 'variant' => 'icon'])
@php
    $text = $text ?: $title;
    $u = rawurlencode($url);
    $t = rawurlencode($text);
    $fbAppId = setting('social.facebook_app_id');
    $links = [
        'whatsapp' => ['https://api.whatsapp.com/send?text='.rawurlencode($text."\n".$url), 'WhatsApp', 'whatsapp', 'bg-[#25D366] text-white'],
        'facebook' => ['https://www.facebook.com/sharer/sharer.php?u='.$u, 'Facebook', 'facebook', 'bg-[#1877F2] text-white'],
        'x' => ['https://twitter.com/intent/tweet?text='.$t.'&url='.$u, 'X (Twitter)', 'brand-x', 'bg-black text-white'],
        'telegram' => ['https://t.me/share/url?url='.$u.'&text='.$t, 'Telegram', 'telegram', 'bg-[#229ED9] text-white'],
        'linkedin' => ['https://www.linkedin.com/sharing/share-offsite/?url='.$u, 'LinkedIn', 'linkedin', 'bg-[#0A66C2] text-white'],
        'email' => ['mailto:?subject='.rawurlencode($title).'&body='.rawurlencode($text."\n".$url), __('directory.share.email'), 'mail', 'bg-navy-900 text-gold-200'],
    ];
    $config = ['url' => $url, 'title' => $title, 'text' => $text, 'trackUrl' => $trackUrl, 'fbAppId' => $fbAppId,
        'copied' => __('common.link_copied'), 'instagram' => __('directory.share.instagram_hint'), 'messenger' => __('directory.share.messenger_hint')];
@endphp
<div x-data="shareMenu(@js($config))" {{ $attributes->class('relative') }} @keydown.escape.window="open = false" @click.outside="open = false">
    @if($variant === 'icon')
        <button type="button" @click="toggle()" :aria-expanded="open.toString()" aria-haspopup="true"
            class="btn-icon size-11 rounded-xl border border-navy-200 bg-white text-navy-900 hover:bg-navy-50" aria-label="{{ __('business.share') }}">
            <x-icon name="share-2" class="size-[1.15rem]" />
        </button>
        <div x-show="open" x-cloak x-transition.origin.top
            class="absolute end-0 top-full z-40 mt-2 w-72 rounded-2xl border border-navy-100 bg-white p-3 shadow-lift">
            <p class="px-1 pb-2 text-xs font-semibold tracking-wide text-muted-strong">{{ __('directory.share.title') }}</p>
            @include('components.partials.share-grid')
        </div>
    @else
        @include('components.partials.share-grid')
    @endif
</div>
