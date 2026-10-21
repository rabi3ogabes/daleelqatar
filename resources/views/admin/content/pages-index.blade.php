<x-layouts.dashboard area="admin" :title="__('admin.content.pages')" :subtitle="__('admin.content.pages_hint')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.pages')]]">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($pages as $page)
            <a href="{{ route('admin.pages.edit', $page) }}" class="card card-hover flex items-start gap-4 p-5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-700"><x-icon name="file-text" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-navy-900">{{ $page->t('title') }}</span>
                    <span class="block text-xs text-muted-strong" dir="ltr">/{{ $page->slug }}</span>
                    <span class="mt-2 flex items-center gap-2 text-xs">
                        <span @class(['badge', 'badge-success' => $page->is_published, 'badge-muted' => ! $page->is_published])>{{ $page->is_published ? __('admin.published') : __('admin.unpublished') }}</span>
                        <span class="text-muted">{{ __('pages.updated', ['date' => format_date($page->updated_at)]) }}</span>
                    </span>
                </span>
            </a>
        @empty
            <x-empty-state icon="file-text" :title="__('dashboard.no_data')" class="md:col-span-2 xl:col-span-3" />
        @endforelse
    </div>
</x-layouts.dashboard>
