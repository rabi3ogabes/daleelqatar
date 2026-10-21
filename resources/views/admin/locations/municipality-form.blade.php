@php $editing = $municipality->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? $municipality->t('name') : __('admin.locations.create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.locations.title'), 'url' => route('admin.municipalities.index')], ['label' => $editing ? $municipality->t('name') : __('admin.locations.create')]]">
    <form method="POST" action="{{ $editing ? route('admin.municipalities.update', $municipality) : route('admin.municipalities.store') }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <x-dash.panel :title="__('admin.locations.municipality')">
            <div class="grid gap-5">
                <x-form.bilingual name="name" :label="__('admin.name')" :model="$municipality" required maxlength="120" />
                <x-form.input name="slug" dir="ltr" :label="__('admin.slug')" :value="$municipality->slug" :hint="__('admin.slug_hint')" />
                <x-form.bilingual name="description" type="textarea" rows="3" :label="__('business_form.description')" :model="$municipality" />
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.input name="latitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.latitude')" :value="$municipality->latitude" />
                    <x-form.input name="longitude" type="number" step="0.0000001" dir="ltr" :label="__('business_form.longitude')" :value="$municipality->longitude" />
                </div>
            </div>
        </x-dash.panel>
        <aside class="space-y-6">
            <div class="card space-y-5 p-5">
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$municipality->is_active ?? true" />
                <x-form.toggle name="is_featured" :label="__('admin.locations.featured_home')" :checked="$municipality->is_featured" />
                <x-form.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$municipality->sort_order" />
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.municipalities.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
            @if($editing)
                <form method="POST" action="{{ route('admin.municipalities.destroy', $municipality) }}" data-confirm="{{ __('admin.locations.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}" class="card p-5">
                    @csrf @method('DELETE')
                    <p class="mb-3 text-xs text-muted-strong">{{ __('admin.locations.delete_hint') }}</p>
                    <button class="btn btn-danger-outline w-full"><x-icon name="trash-2" class="size-4" />{{ __('common.delete') }}</button>
                </form>
            @endif
        </aside>
    </form>
</x-layouts.dashboard>
