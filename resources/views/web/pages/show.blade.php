<x-layouts.app>
    <x-page-hero :title="$page->t('title')" :breadcrumbs="[['label' => $page->t('title')]]" icon="{{ $page->slug === 'about' ? 'landmark' : 'file-text' }}" />
    <div class="container-app py-12">
        <article class="card mx-auto max-w-3xl p-6 sm:p-10">
            <div class="prose-dq">{!! $page->renderedBody() !!}</div>
            <p class="mt-10 border-t border-navy-50 pt-4 text-xs text-muted">{{ __('pages.updated', ['date' => format_date($page->updated_at)]) }}</p>
        </article>
        @if($page->slug === 'about')
            <div class="mx-auto mt-8 flex max-w-3xl flex-wrap justify-center gap-3">
                <a href="{{ route('search') }}" class="btn btn-primary">{{ __('nav.explore') }}</a>
                <a href="{{ route('add-business') }}" class="btn btn-gold">{{ __('nav.add_business') }}</a>
            </div>
        @endif
    </div>
</x-layouts.app>
