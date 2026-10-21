<x-layouts.app>
    <x-page-hero :title="__('categories.title')" :subtitle="__('categories.subtitle')" icon="layout-grid" :breadcrumbs="[['label' => __('nav.categories')]]" />
    <div class="container-app py-12">
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($categories as $category)
                <article class="card card-hover flex flex-col p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-gold-300"><x-icon :name="$category->icon ?? 'layout-grid'" class="size-7" :stroke="1.5" /></span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-navy-900"><a href="{{ route('categories.show', $category) }}" class="hover:text-gold-800">{{ $category->t('name') }}</a></h2>
                            <p class="mt-0.5 text-xs text-muted-strong">{{ trans_choice('home.businesses_count', $counts[$category->id] ?? 0, ['count' => $counts[$category->id] ?? 0]) }}</p>
                        </div>
                    </div>
                    @if($category->t('description'))<p class="mt-4 line-clamp-2 text-sm text-muted-strong">{{ $category->t('description') }}</p>@endif
                    @if($category->children->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach($category->children as $child)
                                <li><a href="{{ route('categories.show', $child) }}" class="inline-block rounded-full bg-navy-50 px-3 py-1 text-xs font-medium text-navy-700 ring-1 ring-navy-100 transition hover:bg-gold-50 hover:text-gold-800 hover:ring-gold-200">{{ $child->t('name') }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('categories.show', $category) }}" class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold text-gold-700 hover:text-gold-800">{{ __('common.view_all') }}<x-icon name="arrow-right" class="size-4" /></a>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.app>
