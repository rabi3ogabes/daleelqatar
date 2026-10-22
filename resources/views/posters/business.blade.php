@php
    $card = $format === 'card';
    $nameAr = $business->name_ar ?: $business->name_en;
    $nameEn = $business->name_en && $business->name_en !== $nameAr ? $business->name_en : null;
    $meta = collect([$business->category?->t('name', 'ar'), $business->locationLabel('ar')])->filter()->implode(' · ');
    $metaEn = collect([$business->category?->t('name', 'en'), $business->locationLabel('en')])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('poster.title') }} — {{ $business->t('name') }}</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: {{ $card ? '120mm 170mm' : 'A4' }}; margin: 0; }
        html, body { background: #e9ecf2; }
        .sheet { width: {{ $card ? '120mm' : '210mm' }}; min-height: {{ $card ? '170mm' : '297mm' }}; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .qr svg { width: 100%; height: auto; display: block; }
        @media print {
            html, body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { margin: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
        }
        @media screen and (max-width: 860px) {
            .sheet-wrap { transform-origin: top center; transform: scale(calc((100vw - 32px) / {{ $card ? 454 : 794 }})); height: calc({{ $card ? 643 : 1123 }}px * (100vw - 32px) / {{ $card ? 454 : 794 }}); }
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    {{-- Toolbar (screen only) --}}
    <div class="no-print sticky top-0 z-10 border-b border-navy-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ $back }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" class="size-4 rtl:rotate-180" />{{ __('common.back') }}</a>
            <div class="flex rounded-xl border border-navy-100 bg-white p-1" role="group" aria-label="{{ __('poster.format') }}">
                @foreach(['a4' => __('poster.format_a4'), 'card' => __('poster.format_card')] as $f => $label)
                    <a href="{{ request()->fullUrlWithQuery(['format' => $f]) }}" @class(['rounded-lg px-3 py-1.5 text-sm font-medium transition', 'bg-navy-900 text-white' => $format === $f, 'text-muted-strong hover:text-navy-900' => $format !== $f]) @if($format === $f) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ $svgUrl }}" class="btn btn-outline btn-sm"><x-icon name="download" class="size-4" />{{ __('poster.download_qr') }}</a>
                <button type="button" onclick="window.print()" class="btn btn-gold btn-sm"><x-icon name="printer" class="size-4" />{{ __('poster.print') }}</button>
            </div>
        </div>
        <p class="mx-auto max-w-5xl px-4 pb-3 text-xs text-muted-strong">{{ __('poster.tip') }}</p>
    </div>

    <div class="sheet-wrap flex justify-center py-8 print:py-0">
        <article class="sheet relative mx-auto flex flex-col overflow-hidden rounded-2xl bg-white shadow-2xl print:rounded-none" dir="rtl" lang="ar">
            {{-- Header band --}}
            <header class="relative overflow-hidden bg-navy-900 {{ $card ? 'px-6 py-5' : 'px-12 py-9' }}">
                <div class="pattern-lattice absolute inset-0 opacity-60" aria-hidden="true"></div>
                <div class="relative flex items-center justify-between">
                    <x-logo variant="dark" :size="$card ? 'sm' : 'lg'" />
                    <span class="rounded-full border border-gold-400/40 px-3 py-1 text-[0.7rem] font-semibold tracking-wide text-gold-300" dir="ltr">daleelqatar.com</span>
                </div>
            </header>
            <div class="h-1 bg-gradient-to-l from-gold-300 via-gold-500 to-gold-300" aria-hidden="true"></div>

            <div class="flex flex-1 flex-col items-center text-center {{ $card ? 'px-6 pt-5 pb-4' : 'px-14 pt-12 pb-10' }}">
                @if($business->logoUrl())
                    <img src="{{ $business->logoUrl() }}" alt="" class="{{ $card ? 'mb-3 size-14' : 'mb-6 size-24' }} rounded-2xl bg-white object-contain p-1.5 ring-1 ring-navy-100">
                @endif
                <h1 class="font-bold leading-tight text-navy-900 {{ $card ? 'text-xl' : 'text-[2.6rem]' }}" style="font-family: var(--font-arabic)">{{ $nameAr }}</h1>
                @if($nameEn)<p class="mt-1 font-semibold text-navy-700 {{ $card ? 'text-sm' : 'text-xl' }}" dir="ltr" lang="en">{{ $nameEn }}</p>@endif
                @if(! $card && $meta)<p class="mt-3 text-base text-muted-strong">{{ $meta }}</p>@endif

                <p class="font-bold text-navy-900 {{ $card ? 'mt-4 text-base' : 'mt-10 text-3xl' }}" style="font-family: var(--font-arabic)">{{ __('poster.headline', [], 'ar') }}</p>
                <p class="font-semibold text-gold-800 {{ $card ? 'text-xs' : 'mt-1 text-lg' }}" dir="ltr" lang="en">{{ __('poster.headline', [], 'en') }}</p>

                {{-- QR with gold corner accents --}}
                <div class="relative {{ $card ? 'mt-4 w-[58mm] p-3' : 'mt-8 w-[105mm] p-5' }}">
                    @foreach(['top-0 start-0 border-t-4 border-s-4 rounded-ss-2xl', 'top-0 end-0 border-t-4 border-e-4 rounded-se-2xl', 'bottom-0 start-0 border-b-4 border-s-4 rounded-es-2xl', 'bottom-0 end-0 border-b-4 border-e-4 rounded-ee-2xl'] as $corner)
                        <span class="absolute size-10 border-gold-500 {{ $corner }}" aria-hidden="true"></span>
                    @endforeach
                    <div class="qr" aria-label="{{ __('poster.qr_label', ['url' => $displayUrl]) }}">{!! $qr !!}</div>
                </div>
                <p class="mt-3 font-mono text-navy-700 {{ $card ? 'text-[0.65rem]' : 'text-sm' }}" dir="ltr">{{ $displayUrl }}</p>

                @unless($card)
                    <ul class="mt-10 grid w-full grid-cols-3 gap-4">
                        @foreach(['save' => 'heart', 'review' => 'star', 'contact' => 'phone'] as $key => $icon)
                            <li class="rounded-2xl border border-navy-100 bg-ivory/60 px-3 py-4">
                                <span class="mx-auto flex size-11 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon :name="$icon" class="size-5" /></span>
                                <p class="mt-2 font-semibold text-navy-900">{{ __('poster.benefit.'.$key, [], 'ar') }}</p>
                                <p class="text-xs text-muted-strong" dir="ltr" lang="en">{{ __('poster.benefit.'.$key, [], 'en') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endunless
            </div>

            <footer class="flex items-center justify-between border-t border-navy-100 bg-ivory/70 {{ $card ? 'px-6 py-3 text-[0.65rem]' : 'px-12 py-5 text-sm' }} text-muted-strong">
                <span style="font-family: var(--font-arabic)">{{ __('poster.footer', [], 'ar') }}</span>
                <span dir="ltr" lang="en">{{ __('poster.footer', [], 'en') }}</span>
            </footer>
        </article>
    </div>
</body>
</html>
