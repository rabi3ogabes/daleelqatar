<x-layouts.dashboard area="admin" :title="__('admin.locations.title')" :subtitle="__('admin.locations.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.locations.title')]]">
    <x-slot:actions><a href="{{ route('admin.municipalities.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.locations.create') }}</a></x-slot:actions>
    <div class="grid gap-5 lg:grid-cols-2">
        @forelse($municipalities as $m)
            <section class="card overflow-hidden">
                <header class="flex flex-wrap items-center gap-3 border-b border-navy-50 px-5 py-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="map-pin" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="flex flex-wrap items-center gap-2 font-semibold text-navy-900">{{ $m->t('name') }}
                            @unless($m->is_active)<span class="badge badge-muted">{{ __('admin.inactive') }}</span>@endunless
                            @if($m->is_featured)<span class="badge badge-gold">{{ __('admin.featured') }}</span>@endif
                        </h2>
                        <p class="text-xs text-muted-strong"><span dir="ltr">/{{ $m->slug }}</span> · {{ trans_choice('home.businesses_count', $m->businesses_count, ['count' => $m->businesses_count]) }}</p>
                    </div>
                    <a href="{{ route('admin.areas.create', $m) }}" class="btn btn-ghost btn-sm"><x-icon name="plus" class="size-4" />{{ __('admin.locations.add_area') }}</a>
                    <a href="{{ route('admin.municipalities.edit', $m) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                </header>
                <ul class="max-h-80 divide-y divide-navy-50 overflow-y-auto">
                    @forelse($m->areas as $area)
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <span class="min-w-0 flex-1">
                                <span class="font-medium text-navy-900">{{ $area->t('name') }}</span>
                                @unless($area->is_active)<span class="badge badge-muted ms-1">{{ __('admin.inactive') }}</span>@endunless
                                @if($area->is_featured)<span class="badge badge-gold ms-1">{{ __('admin.featured') }}</span>@endif
                                <span class="block text-xs text-muted-strong">{{ trans_choice('home.businesses_count', $area->businesses_count, ['count' => $area->businesses_count]) }}</span>
                            </span>
                            <a href="{{ route('admin.areas.edit', $area) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                            <form method="POST" action="{{ route('admin.areas.destroy', $area) }}" data-confirm="{{ __('admin.locations.confirm_delete_area') }}" data-confirm-label="{{ __('common.delete') }}">@csrf @method('DELETE')
                                <button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}" @disabled($area->businesses_count > 0)><x-icon name="trash-2" class="size-4" /></button>
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-muted-strong">{{ __('admin.locations.no_areas') }}</li>
                    @endforelse
                </ul>
            </section>
        @empty
            <x-empty-state icon="map-pin" :title="__('admin.locations.empty')" class="lg:col-span-2" />
        @endforelse
    </div>
</x-layouts.dashboard>
