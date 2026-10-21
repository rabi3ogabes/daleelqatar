<x-layouts.dashboard area="admin" :title="__('admin.businesses.create')" :subtitle="__('admin.businesses.create_hint')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.businesses.title'), 'url' => route('admin.businesses.index')], ['label' => __('admin.businesses.create')]]">
    <form method="POST" action="{{ route('admin.businesses.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf
        <div>@include('partials.business-form.fields', ['business' => null])</div>
        <aside class="space-y-6">
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                <x-form.input name="owner_email" type="email" dir="ltr" :label="__('admin.businesses.owner_email')" :hint="__('admin.businesses.owner_email_hint')" />
                <x-form.toggle name="publish" :label="__('admin.businesses.publish_now')" :hint="__('admin.businesses.publish_now_hint')" />
                <button type="submit" class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('admin.businesses.save') }}</button>
                <a href="{{ route('admin.businesses.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
