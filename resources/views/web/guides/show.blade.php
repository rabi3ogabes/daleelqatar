@php
    $place = $guide->area?->t('name') ?? $guide->municipality?->t('name');
    $crumbs = [['label' => __('guides.index_title'), 'url' => route('guides.index')], ['label' => $guide->t('title')]];
@endphp
<x-layouts.app>
    <x-page-hero :title="$guide->t('title')" :icon="$guide->category?->icon ?: 'book-marked'" :eyebrow="__('guides.eyebrow')" :breadcrumbs="$crumbs">
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            @if($guide->category)<span class="rounded-full bg-white/10 px-3 py-1 text-white/85">{{ $guide->category->t('name') }}</span>@endif
            @if($place)<span class="rounded-full bg-white/10 px-3 py-1 text-white/85"><x-icon name="map-pin" class="inline size-3.5" /> {{ $place }}</span>@endif
            @if($guide->open_24h)<span class="rounded-full bg-gold-400 px-3 py-1 font-semibold text-navy-950">{{ __('guides.open_24h') }}</span>@endif
            @if($guide->verified_only)<span class="rounded-full bg-white/10 px-3 py-1 text-white/85"><x-icon name="badge-check" class="inline size-3.5" /> {{ __('guides.verified_only') }}</span>@endif
            <span class="rounded-full bg-white/10 px-3 py-1 text-white/85">{{ __('guides.updated', ['date' => format_date($guide->updated_at, 'MMMM YYYY')]) }}</span>
        </div>
    </x-page-hero>

    <div class="container-app grid gap-10 py-12 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @unless($guide->isPublished())
                <p class="mb-6 rounded-2xl bg-warning-soft p-4 text-sm text-warning">{{ __('guides.draft_preview') }}</p>
            @endunless
            @if($guide->t('intro'))
                <div class="prose-dq mb-10 space-y-4 text-base leading-8 text-charcoal">
                    @foreach(preg_split('/\R{2,}/', trim($guide->t('intro'))) as $para)<p>{{ $para }}</p>@endforeach
                </div>
            @endif

            @if($listings->isEmpty())
                <x-empty-state icon="text-search" :title="__('guides.no_listings')" />
            @else
                <ol class="space-y-5" data-impressions="{{ $impressions }}">
                    @foreach($listings as $business)
                        <li class="relative flex gap-4">
                            <span class="mt-5 flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-sm font-bold text-gold-300 tabular-nums" aria-hidden="true">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1"><x-business-card :business="$business" layout="list" :favorited="in_array($business->id, $favoriteIds, true)" :eager="$loop->index < 2" /></div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <section class="card p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-icon name="shield-check" class="size-4 text-gold-600" />{{ __('guides.method_title') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-strong">{{ __('guides.method_text') }}</p>
                <a href="{{ route('editorial') }}" class="mt-3 inline-flex text-xs font-medium text-gold-700 hover:underline">{{ __('guides.method_link') }}</a>
            </section>

            @if($guide->category && \App\Support\Quotes\QuoteService::enabled())
                <a href="{{ route('quotes.create', ['category' => $guide->category->slug]) }}" class="block rounded-2xl bg-navy-900 p-5 text-white transition hover:bg-navy-800">
                    <x-icon name="message-square-quote" class="size-6 text-gold-300" />
                    <p class="mt-3 font-semibold">{{ __('quotes.cta.category_title', ['category' => $guide->category->t('name')]) }}</p>
                    <p class="mt-1 text-sm text-white/70">{{ __('quotes.cta.category_text') }}</p>
                    <span class="btn btn-gold btn-sm mt-4">{{ __('quotes.cta.button') }}</span>
                </a>
            @endif

            @if($related->isNotEmpty())
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-navy-900">{{ __('guides.related') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach($related as $other)
                            <li><a href="{{ route('guides.show', $other) }}" class="flex items-center gap-2 text-sm text-navy-900 hover:text-gold-800"><x-icon name="book-marked" class="size-4 text-gold-600" />{{ $other->t('title') }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
