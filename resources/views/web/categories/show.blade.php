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
        <x-results :results="$results" :criteria="$criteria" :action="route('categories.show', $category)" :show-category="false"
            :municipalities="$municipalities" :layout="request('view') === 'list' ? 'list' : 'grid'" :favorite-ids="$favoriteIds" />
    </div>
</x-layouts.app>
