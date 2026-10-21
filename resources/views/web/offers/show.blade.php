@php $business = $offer->business; @endphp
<x-layouts.app>
    <x-page-hero :title="$offer->t('title')" :subtitle="__('offers.by', ['business' => $business->t('name')])" icon="tag"
        :breadcrumbs="[['label' => __('nav.offers'), 'url' => route('offers.index')], ['label' => $offer->t('title')]]" />
    <div class="container-app grid gap-8 py-12 lg:grid-cols-12">
        <article class="card overflow-hidden lg:col-span-8">
            <div class="relative aspect-[16/8] bg-navy-900">
                @if($offer->imageUrl())
                    <img src="{{ $offer->imageUrl() }}" alt="{{ $offer->t('title') }}" class="absolute inset-0 size-full object-cover">
                @else
                    <div class="pattern-lattice absolute inset-0 bg-gradient-to-br from-navy-800 to-navy-950"></div>
                @endif
                @if($offer->t('discount_label'))<span class="absolute start-5 top-5 rounded-2xl bg-gold-400 px-4 py-2 text-xl font-bold text-navy-950 shadow-gold">{{ $offer->t('discount_label') }}</span>@endif
            </div>
            <div class="card-pad">
                @unless($live)
                    <p class="mb-4 flex items-center gap-2 rounded-xl bg-warning-soft px-4 py-2.5 text-sm text-warning"><x-icon name="clock" class="size-4" />{{ $offer->isExpired() ? __('offers.expired') : $offer->status->label() }}</p>
                @endunless
                <p class="flex flex-wrap items-center gap-4 text-sm text-muted-strong">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="size-4 text-gold-600" />{{ format_date($offer->starts_at) }} – {{ format_date($offer->ends_at) }}</span>
                </p>
                @if($offer->t('description'))<div class="prose-dq mt-5 whitespace-pre-line">{{ $offer->t('description') }}</div>@endif
                @if($offer->t('terms'))
                    <div class="mt-6 rounded-xl bg-navy-50/60 p-4">
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('offers.terms') }}</h2>
                        <p class="mt-2 text-sm whitespace-pre-line text-muted-strong">{{ $offer->t('terms') }}</p>
                    </div>
                @endif
            </div>
        </article>
        <aside class="lg:col-span-4">
            <a href="{{ route('business.show', $business) }}" class="card card-hover block overflow-hidden">
                <x-business-cover :business="$business" class="aspect-[16/9]" />
                <div class="p-5">
                    <p class="text-xs text-gold-700">{{ $business->category?->t('name') }}</p>
                    <p class="mt-1 flex items-center gap-1.5 font-semibold text-navy-900">{{ $business->t('name') }}@if($business->is_verified)<x-icon name="badge-check" class="size-4 text-gold-600" />@endif</p>
                    <p class="mt-1 text-sm text-muted-strong">{{ $business->locationLabel() }}</p>
                    <span class="btn btn-primary mt-4 w-full">{{ __('offers.view_business') }}</span>
                </div>
            </a>
        </aside>
    </div>
</x-layouts.app>
