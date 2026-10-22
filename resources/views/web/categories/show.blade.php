@php
    $crumbs = [['label' => __('nav.categories'), 'url' => route('categories.index')]];
    if ($category->parent) { $crumbs[] = ['label' => $category->parent->t('name'), 'url' => route('categories.show', $category->parent)]; }
    $crumbs[] = ['label' => $category->t('name')];
    $root = $category->parent ?? $category;
@endphp
<x-layouts.app>
    <x-page-hero :title="$category->t('name')" :subtitle="$category->t('description')" :icon="$category->icon ?? 'layout-grid'" :breadcrumbs="$crumbs">
        @php $subs = $category->parent ? $category->parent->children()->active()->get() : $category->children; @endphp
        @if($subs->isNotEmpty())
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('categories.subcategories') }}">
                <a href="{{ route('categories.show', $root) }}" @class(['rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition', 'bg-gold-400 text-navy-950 ring-gold-400' => $category->is($root), 'bg-white/5 text-white/80 ring-white/15 hover:bg-white/15' => ! $category->is($root)])>{{ __('common.all') }}</a>
                @foreach($subs as $sub)
                    <a href="{{ route('categories.show', $sub) }}" @class(['rounded-full px-3.5 py-1.5 text-xs font-medium ring-1 transition', 'bg-gold-400 text-navy-950 ring-gold-400' => $category->is($sub), 'bg-white/5 text-white/80 ring-white/15 hover:bg-white/15' => ! $category->is($sub)])>{{ $sub->t('name') }}</a>
                @endforeach
            </nav>
        @endif
    </x-page-hero>
    <div class="container-app py-10">
        @if(\App\Support\Quotes\QuoteService::enabled())
            <a href="{{ route('quotes.create', ['category' => $category->slug]) }}" class="group mb-8 flex flex-col gap-4 rounded-2xl border border-gold-200 bg-gradient-to-br from-gold-50 to-white p-5 transition hover:border-gold-300 hover:shadow-soft sm:flex-row sm:items-center">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-gold-300"><x-icon name="message-square-quote" class="size-6" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-navy-900">{{ __('quotes.cta.category_title', ['category' => $category->t('name')]) }}</span>
                    <span class="mt-0.5 block text-sm text-muted-strong">{{ __('quotes.cta.category_text') }}</span>
                </span>
                <span class="btn btn-gold shrink-0">{{ __('quotes.cta.button') }}<x-icon name="arrow-right" class="size-4 rtl:rotate-180" /></span>
            </a>
        @endif
        <x-results :results="$results" :criteria="$criteria" :action="route('categories.show', $category)" :show-category="false"
            :municipalities="$municipalities" :layout="request('view') === 'list' ? 'list' : 'grid'" :favorite-ids="$favoriteIds" />
    </div>
</x-layouts.app>
