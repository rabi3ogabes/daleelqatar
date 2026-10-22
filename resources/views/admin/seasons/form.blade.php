@php $editing = $season->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? $season->t('name') : __('seasons.admin.create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('seasons.admin.title'), 'url' => route('admin.seasons.index')], ['label' => $editing ? $season->t('name') : __('seasons.admin.create')]]">
    <form method="POST" action="{{ $editing ? route('admin.seasons.update', $season) : route('admin.seasons.store') }}" class="card max-w-3xl space-y-5 p-5 sm:p-6">
        @csrf @if($editing) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="name_ar" dir="rtl" :label="__('seasons.admin.name_ar')" :value="$season->name_ar" required maxlength="120" placeholder="رمضان 2027" />
            <x-form.input name="name_en" dir="ltr" :label="__('seasons.admin.name_en')" :value="$season->name_en" required maxlength="120" placeholder="Ramadan 2027" />
        </div>
        <x-form.select name="kind" :label="__('seasons.admin.kind')" :value="$season->kind" required
            :options="collect(\App\Models\SeasonalPeriod::KINDS)->mapWithKeys(fn ($k) => [$k => __('seasons.kind.'.$k)])->all()" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="starts_on" type="date" dir="ltr" :label="__('seasons.admin.starts_on')" :value="$season->starts_on?->toDateString()" required />
            <x-form.input name="ends_on" type="date" dir="ltr" :label="__('seasons.admin.ends_on')" :value="$season->ends_on?->toDateString()" required />
        </div>
        <x-form.toggle name="is_active" :label="__('seasons.admin.active')" :hint="__('seasons.admin.active_hint')" :checked="(bool) old('is_active', $season->is_active)" />
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.seasons.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
        </div>
    </form>
</x-layouts.dashboard>
