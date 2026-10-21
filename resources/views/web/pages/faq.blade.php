<x-layouts.app>
    <x-page-hero :title="__('pages.faq_title')" :subtitle="__('pages.faq_subtitle')" icon="circle-help" :breadcrumbs="[['label' => __('nav.faq')]]" />
    <div class="container-app py-12">
        <div class="mx-auto max-w-3xl space-y-10">
            @foreach(['general', 'users', 'owners'] as $audience)
                @if($faqs->has($audience))
                    <section aria-labelledby="faq-{{ $audience }}">
                        <h2 id="faq-{{ $audience }}" class="text-lg font-semibold">{{ __('pages.faq_'.$audience) }}</h2>
                        <div class="divider-gold mt-2 mb-4"></div>
                        <div class="space-y-3">
                            @foreach($faqs[$audience] as $faq)
                                <details class="card group p-5 open:shadow-lift">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-navy-900">
                                        {{ $faq->t('question') }}
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-700 transition group-open:rotate-45"><x-icon name="plus" class="size-4" /></span>
                                    </summary>
                                    <p class="mt-3 text-sm leading-7 text-muted-strong">{{ $faq->t('answer') }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
            <div class="rounded-2xl bg-navy-900 p-6 text-center text-white">
                <p class="font-semibold">{{ __('pages.still_questions') }}</p>
                <a href="{{ route('contact') }}" class="btn btn-gold mt-4">{{ __('pages.contact_team') }}</a>
            </div>
        </div>
    </div>
</x-layouts.app>
