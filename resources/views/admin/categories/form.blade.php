@php $editing = $category->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? __('admin.categories.edit_title', ['name' => $category->t('name')]) : __('admin.categories.create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.categories.title'), 'url' => route('admin.categories.index')], ['label' => $editing ? $category->t('name') : __('admin.categories.create')]]">
    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <div class="space-y-6">
            <x-dash.panel :title="__('business_form.basics')">
                <div class="grid gap-5">
                    <x-form.bilingual name="name" :label="__('admin.name')" :model="$category" required maxlength="120" />
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-form.select name="parent_id" :label="__('admin.categories.parent')" :options="$parents->mapWithKeys(fn ($p) => [$p->id => $p->t('name')])->all()" :value="$category->parent_id" :placeholder="__('admin.categories.top_level')" />
                        <x-form.input name="slug" dir="ltr" :label="__('admin.slug')" :value="$category->slug" :hint="__('admin.slug_hint')" />
                    </div>
                    <x-form.bilingual name="description" type="textarea" rows="3" :label="__('business_form.description')" :model="$category" maxlength="2000" />
                </div>
            </x-dash.panel>
            <x-dash.panel :title="__('admin.categories.icon')" :subtitle="__('admin.categories.icon_hint')">
                <div class="grid max-h-72 grid-cols-6 gap-2 overflow-y-auto pe-1 sm:grid-cols-10" role="radiogroup" aria-label="{{ __('admin.categories.icon') }}">
                    @foreach($icons as $icon)
                        <label class="cursor-pointer" title="{{ $icon }}">
                            <input type="radio" name="icon" value="{{ $icon }}" class="peer sr-only" @checked(old('icon', $category->icon) === $icon)>
                            <span class="flex aspect-square items-center justify-center rounded-xl border border-navy-100 bg-white text-navy-700 transition peer-checked:border-gold-500 peer-checked:bg-navy-900 peer-checked:text-gold-300 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-300 hover:border-gold-300"><x-icon :name="$icon" class="size-5" /></span>
                            <span class="sr-only">{{ $icon }}</span>
                        </label>
                    @endforeach
                </div>
                @error('icon')<p class="error">{{ $message }}</p>@enderror
            </x-dash.panel>
            <x-dash.panel :title="__('business_form.seo')">
                <div class="grid gap-5">
                    <x-form.bilingual name="meta_title" :label="__('business_form.meta_title')" :model="$category" maxlength="160" />
                    <x-form.bilingual name="meta_description" type="textarea" rows="2" :label="__('business_form.meta_description')" :model="$category" maxlength="300" />
                </div>
            </x-dash.panel>
        </div>
        <aside class="space-y-6">
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$category->is_active ?? true" :hint="__('admin.active_hint')" />
                <x-form.toggle name="is_featured" :label="__('admin.categories.featured_home')" :checked="$category->is_featured" />
                <x-form.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$category->sort_order" />
                <x-form.image name="image" :label="__('admin.image')" :current="$category->imageUrl()" />
                @if($category->image_path)<x-form.checkbox name="remove_image" :label="__('admin.remove_image')" />@endif
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
            @if($editing)
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="{{ __('admin.categories.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}" class="card p-5">
                    @csrf @method('DELETE')
                    <p class="mb-3 text-xs text-muted-strong">{{ __('admin.categories.delete_hint') }}</p>
                    <button class="btn btn-danger-outline w-full"><x-icon name="trash-2" class="size-4" />{{ __('common.delete') }}</button>
                </form>
            @endif
        </aside>
    </form>
</x-layouts.dashboard>
