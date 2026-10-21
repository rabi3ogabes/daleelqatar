@php $editing = $banner->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? $banner->t('title') : __('admin.content.banner_create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.banners'), 'url' => route('admin.banners.index')], ['label' => $editing ? $banner->t('title') : __('admin.content.banner_create')]]">
    <form method="POST" action="{{ $editing ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <x-dash.panel>
            <div class="grid gap-5">
                <x-form.bilingual name="title" :label="__('admin.content.banner_title')" :model="$banner" required maxlength="160" />
                <x-form.bilingual name="subtitle" :label="__('admin.content.subtitle')" :model="$banner" maxlength="300" />
                <x-form.bilingual name="cta_label" :label="__('admin.content.cta_label')" :model="$banner" maxlength="60" />
                <x-form.input name="link_url" dir="ltr" :label="__('admin.content.link')" :value="$banner->link_url" :hint="__('admin.content.link_hint')" />
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.input name="starts_at" type="datetime-local" :label="__('admin.offers.starts_at')" :value="$banner->starts_at?->format('Y-m-d\TH:i')" />
                    <x-form.input name="ends_at" type="datetime-local" :label="__('admin.offers.ends_at')" :value="$banner->ends_at?->format('Y-m-d\TH:i')" />
                </div>
                <x-form.image name="image" :label="__('admin.image')" :current="$banner->imageUrl()" />
            </div>
        </x-dash.panel>
        <aside>
            <div class="card space-y-5 p-5">
                <x-form.select name="placement" :label="__('admin.content.placement')" :options="collect(\App\Http\Controllers\Admin\BannerController::PLACEMENTS)->mapWithKeys(fn ($p) => [$p => __('admin.content.placements.'.$p)])->all()" :value="$banner->placement" required />
                <x-form.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$banner->sort_order" />
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$banner->is_active" />
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.banners.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
