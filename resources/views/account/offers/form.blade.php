@php
    $editing = $offer !== null;
    $fmt = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i') : null;
@endphp
<x-layouts.account :title="$editing ? __('account.offers.edit_title') : __('account.offers.create_title')" :subtitle="__('account.offers.moderation_note')"
    :breadcrumbs="[['label' => __('account.offers.title'), 'url' => route('account.offers.index')], ['label' => $editing ? __('account.offers.edit_title') : __('account.offers.create_title')]]">
    <form method="POST" action="{{ $editing ? route('account.offers.update', $offer->uuid) : route('account.offers.store') }}" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-6" novalidate>
        @csrf
        @if($editing) @method('PUT') @endif

        @if($editing && $offer->status === \App\Enums\OfferStatus::Rejected && $offer->rejection_reason)
            <div class="rounded-2xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger" role="alert"><strong>{{ __('account.offers.rejection_reason') }}:</strong> {{ $offer->rejection_reason }}</div>
        @endif

        <section class="card space-y-5 p-5 sm:p-6">
            @if($editing)
                <p class="text-sm"><span class="text-muted-strong">{{ __('account.offers.business') }}:</span> <strong class="text-navy-900">{{ $offer->business->t('name') }}</strong> <x-status-badge :status="$offer->status" class="ms-2" /></p>
            @else
                <x-form.select name="business_id" :label="__('account.offers.business')" :options="$businesses->mapWithKeys(fn ($b) => [$b->uuid => $b->t('name')])->all()" :value="$selected" :placeholder="__('account.offers.choose_business')" required />
            @endif
            <x-form.bilingual name="title" :label="__('account.offers.offer_title')" :model="$offer" required maxlength="160" />
            <x-form.bilingual name="discount_label" :label="__('account.offers.discount_label')" :model="$offer" maxlength="40" :hint="__('account.offers.discount_hint')" />
            <x-form.bilingual name="description" type="textarea" rows="4" :label="__('account.offers.description')" :model="$offer" maxlength="2000" />
            <x-form.bilingual name="terms" type="textarea" rows="3" :label="__('account.offers.terms')" :model="$offer" maxlength="2000" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="starts_at" type="datetime-local" dir="ltr" :label="__('account.offers.starts_at')" :value="$fmt($offer?->starts_at) ?? now()->format('Y-m-d\TH:i')" required />
                <x-form.input name="ends_at" type="datetime-local" dir="ltr" :label="__('account.offers.ends_at')" :value="$fmt($offer?->ends_at) ?? now()->addDays(14)->format('Y-m-d\TH:i')" required />
            </div>
            <x-form.image name="image" :label="__('account.offers.image')" :current="$offer?->imageUrl()" />
            <x-form.toggle name="is_active" :label="__('account.offers.active')" :hint="__('account.offers.active_hint')" :checked="$offer?->is_active ?? true" />
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('account.offers.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <div class="flex flex-col gap-2 sm:flex-row">
                @if(! $editing || $offer->status === \App\Enums\OfferStatus::Draft)
                    <button type="submit" name="intent" value="draft" class="btn btn-outline btn-lg">{{ __('account.offers.save_draft') }}</button>
                    <button type="submit" name="intent" value="submit" class="btn btn-gold btn-lg"><x-icon name="send" class="size-5" />{{ __('account.offers.publish') }}</button>
                @else
                    <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="size-5" />{{ __('account.offers.save') }}</button>
                @endif
            </div>
        </div>
    </form>
</x-layouts.account>
