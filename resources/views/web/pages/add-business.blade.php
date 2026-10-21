<x-layouts.app>
    <section class="relative isolate overflow-hidden bg-navy-950">
        <img src="{{ asset('images/doha-skyline.svg') }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-60" aria-hidden="true">
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-950/60 to-navy-950/30" aria-hidden="true"></div>
        <div class="container-app py-16 text-center sm:py-24">
            <x-breadcrumbs :items="[['label' => __('nav.add_business')]]" dark class="flex justify-center" />
            <p class="eyebrow mt-6 !text-gold-300">{{ __('home.cta_eyebrow') }}</p>
            <h1 class="mx-auto mt-3 max-w-3xl text-3xl font-bold !text-white sm:text-5xl">{{ __('pages.add_title') }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-white/75">{{ __('pages.add_subtitle') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ auth()->check() ? route('account.businesses.create') : route('register') }}" class="btn btn-gold btn-lg">{{ __('pages.add_start') }}<x-icon name="arrow-right" class="size-5" /></a>
                <a href="{{ route('search') }}" class="btn btn-outline-light btn-lg">{{ __('pages.add_already_listed') }}</a>
            </div>
        </div>
    </section>
    <section class="container-app py-16">
        <ol class="grid gap-5 md:grid-cols-3">
            @foreach([1 => 'user-plus', 2 => 'store', 3 => 'badge-check'] as $n => $icon)
                <li class="card card-pad relative">
                    <span class="absolute end-5 top-5 text-5xl font-bold text-navy-50">{{ $n }}</span>
                    <span class="relative flex size-12 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon :name="$icon" /></span>
                    <h2 class="relative mt-5 font-semibold">{{ __('pages.add_step_'.$n) }}</h2>
                    <p class="relative mt-2 text-sm text-muted-strong">{{ __('pages.add_step_'.$n.'_text') }}</p>
                </li>
            @endforeach
        </ol>
        <div class="mt-12 grid gap-8 lg:grid-cols-2">
            <div class="card card-pad">
                <h2 class="text-lg font-semibold">{{ __('home.cta_title') }}</h2>
                <ul class="mt-5 space-y-3">
                    @foreach(['cta_point_1', 'cta_point_2', 'cta_point_3'] as $point)
                        <li class="flex items-center gap-3 text-sm"><span class="flex size-6 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-icon name="check" class="size-3.5" :stroke="2.5" /></span>{{ __('home.'.$point) }}</li>
                    @endforeach
                    @foreach([1, 2, 4] as $n)
                        <li class="flex items-center gap-3 text-sm"><span class="flex size-6 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-icon name="check" class="size-3.5" :stroke="2.5" /></span>{{ __('home.trust_'.$n.'_title') }}</li>
                    @endforeach
                </ul>
            </div>
            @if($faqs->isNotEmpty())
                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <details class="card group p-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-navy-900">{{ $faq->t('question') }}<x-icon name="plus" class="size-4 text-gold-700 transition group-open:rotate-45" /></summary>
                            <p class="mt-3 text-sm leading-7 text-muted-strong">{{ $faq->t('answer') }}</p>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
