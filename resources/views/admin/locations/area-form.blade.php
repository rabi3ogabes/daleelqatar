@php $editing = $area->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? $area->t('name') : __('admin.locations.add_area')" :subtitle="$municipality->t('name')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.locations.title'), 'url' => route('admin.municipalities.index')], ['label' => $municipality->t('name')], ['label' => $editing ? $area->t('name') : __('admin.locations.add_area')]]">
    <form method="POST" action="{{ $editing ? route('admin.areas.update', $area) : route('admin.areas.store', $municipality) }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <x-dash.panel :title="__('admin.locations.area')">
            <div class="grid gap-5">
                <x-form.select name="municipality_id" :label="__('search.municipality')" :options="$municipalities->mapWithKeys(fn ($m) => [$m->id => $m->t('name')])->all()" :value="$area->municipality_id" required />
                <x-form.bilingual name="name" :label="__('admin.name')" :model="$area" required maxlength="120" />
                <x-form.input name="slug" dir="ltr" :label="__('admin.slug')" :value="$area->slug" :hint="__('admin.slug_hint')" />
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.input name="latitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.latitude')" :value="$area->latitude" />
                    <x-form.input name="longitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.longitude')" :value="$area->longitude" />
                </div>
            </div>
        </x-dash.panel>
        <aside>
            <div class="card space-y-5 p-5">
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$area->is_active ?? true" />
                <x-form.toggle name="is_featured" :label="__('admin.locations.featured_home')" :checked="$area->is_featured" />
                <x-form.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$area->sort_order" />
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.municipalities.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
