<x-layouts.dashboard area="admin" :title="__('admin.businesses.edit_title', ['name' => $business->t('name')])" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.businesses.title'), 'url' => route('admin.businesses.index')], ['label' => $business->t('name'), 'url' => route('admin.businesses.show', $business->id)], ['label' => __('common.edit')]]">
    <form method="POST" action="{{ route('admin.businesses.update', $business->id) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @method('PUT')
        <div>@include('partials.business-form.fields', ['business' => $business])</div>
        <aside class="space-y-6">
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                <div>
                    <p class="text-xs text-muted-strong">{{ __('common.status') }}</p>
                    <div class="mt-1"><x-status-badge :status="$business->status" /></div>
                </div>
                <x-form.input name="slug" dir="ltr" :label="__('admin.businesses.slug')" :value="$business->slug" :hint="__('admin.businesses.slug_hint')" required />
                <button type="submit" class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save_changes') }}</button>
                <a href="{{ route('admin.businesses.show', $business->id) }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
