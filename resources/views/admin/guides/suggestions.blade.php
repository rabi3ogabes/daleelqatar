<x-layouts.dashboard area="admin" :title="__('guides.admin.ideas_title')" :subtitle="__('guides.admin.ideas_subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('guides.admin.title'), 'url' => route('admin.guides.index')], ['label' => __('guides.admin.ideas')]]">
    <a href="{{ route('admin.guides.create', ['open_24h' => 1]) }}" class="card card-hover mb-5 flex items-center gap-4 p-5">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-navy-900 text-gold-300"><x-icon name="clock" class="size-6" /></span>
        <span class="flex-1"><span class="block font-semibold text-navy-900">{{ __('guides.admin.idea_24h') }}</span><span class="block text-sm text-muted-strong">{{ __('guides.admin.idea_24h_text') }}</span></span>
        <x-icon name="chevron-right" class="size-5 text-muted rtl:rotate-180" />
    </a>
    @if($suggestions === [])
        <x-empty-state icon="sparkles" :title="__('guides.admin.no_ideas')" :text="__('guides.admin.no_ideas_text')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($suggestions as $s)
                <a href="{{ route('admin.guides.create', ['category_id' => $s['category']->id, 'municipality_id' => $s['municipality']->id]) }}" class="card card-hover flex items-center gap-3 p-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-gold-50 text-gold-700 ring-1 ring-gold-200"><x-icon :name="$s['category']->icon ?: 'layers'" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy-900">{{ __('guides.draft.title_in', ['what' => $s['category']->t('name'), 'place' => $s['municipality']->t('name')]) }}</span>
                        <span class="block text-xs text-muted-strong">{{ trans_choice('guides.admin.complete_listings', $s['count'], ['count' => $s['count']]) }}</span>
                    </span>
                    <x-icon name="plus" class="size-4 text-gold-700" />
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
