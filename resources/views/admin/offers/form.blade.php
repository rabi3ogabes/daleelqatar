@php $editing = $offer->exists; @endphp
<x-layouts.dashboard area="admin" :title="$editing ? $offer->t('title') : __('admin.offers.create')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.offers.title'), 'url' => route('admin.offers.index')], ['label' => $editing ? $offer->t('title') : __('admin.offers.create')]]">
    <form method="POST" action="{{ $editing ? route('admin.offers.update', $offer) : route('admin.offers.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @if($editing) @method('PUT') @endif
        <x-dash.panel :title="__('admin.offers.details')">
            <div class="grid gap-5">
                @if($editing)
                    <p class="text-sm text-muted-strong">{{ __('admin.businesses.business') }}: <strong class="text-navy-900">{{ $offer->business?->t('name') }}</strong></p>
                @else
                    <x-form.select name="business_id" :label="__('admin.businesses.business')" :options="$businesses" :placeholder="__('admin.offers.choose_business')" required />
                @endif
                <x-form.bilingual name="title" :label="__('admin.offers.offer_title')" :model="$offer" required maxlength="160" />
                <x-form.bilingual name="discount_label" :label="__('admin.offers.discount_label')" :model="$offer" maxlength="40" :hint="__('admin.offers.discount_hint')" />
                <x-form.bilingual name="description" type="textarea" rows="3" :label="__('business_form.description')" :model="$offer" maxlength="3000" />
                <x-form.bilingual name="terms" type="textarea" rows="3" :label="__('offers.terms')" :model="$offer" maxlength="3000" />
                <div class="grid gap-5 md:grid-cols-2">
                    <x-form.input name="starts_at" type="datetime-local" :label="__('admin.offers.starts_at')" :value="$offer->starts_at?->format('Y-m-d\TH:i')" required />
                    <x-form.input name="ends_at" type="datetime-local" :label="__('admin.offers.ends_at')" :value="$offer->ends_at?->format('Y-m-d\TH:i')" required />
                </div>
            </div>
        </x-dash.panel>
        <aside>
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                @if($editing)<div class="flex items-center justify-between"><span class="text-sm text-muted-strong">{{ __('common.status') }}</span><x-status-badge :status="$offer->status" /></div>@endif
                <x-form.toggle name="is_active" :label="__('admin.active')" :checked="$offer->is_active ?? true" />
                <x-form.toggle name="is_featured" :label="__('admin.featured')" :checked="$offer->is_featured" />
                <x-form.image name="image" :label="__('admin.image')" :current="$offer->imageUrl()" />
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save') }}</button>
                <a href="{{ route('admin.offers.index') }}" class="btn btn-ghost w-full">{{ __('common.cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
