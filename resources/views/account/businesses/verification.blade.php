<x-layouts.account :title="__('account.verification.title', ['name' => $business->t('name')])" :subtitle="__('account.verification.subtitle')"
    :breadcrumbs="[['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => $business->t('name'), 'url' => route('account.businesses.show', $business->uuid)], ['label' => __('account.businesses.verification_cta')]]">
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('account.businesses.verification.store', $business->uuid) }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6 lg:col-span-2" novalidate>
            @csrf
            <x-form.input name="commercial_registration" dir="ltr" :label="__('account.verification.cr')" maxlength="60" />
            <div>
                <label for="f_document" class="label">{{ __('account.verification.document') }} <span class="text-danger">*</span></label>
                <x-form.file name="document" id="f_document" accept="application/pdf,image/jpeg,image/png" required class="rounded-xl border border-dashed border-navy-200 bg-navy-50/40 p-3" />
                <p class="hint">{{ __('account.verification.document_hint', ['types' => __('common.document_requirements', ['size' => round((int) setting('uploads.max_document_kb', 8192) / 1024)])]) }}</p>
            </div>
            <x-form.textarea name="notes" :label="__('account.verification.notes')" rows="3" maxlength="1000" />
            <button type="submit" class="btn btn-primary"><x-icon name="shield-check" class="size-4" />{{ __('account.verification.submit') }}</button>
        </form>
        <aside class="card card-pad h-fit">
            <h2 class="font-semibold text-navy-900">{{ __('account.verification.what_we_check') }}</h2>
            <ul class="mt-4 space-y-3 text-sm text-muted-strong">
                @foreach(['check_1', 'check_2', 'check_3'] as $c)
                    <li class="flex items-start gap-2"><x-icon name="circle-check" class="mt-0.5 size-4 text-success" />{{ __('account.verification.'.$c) }}</li>
                @endforeach
            </ul>
            <p class="mt-5 flex items-start gap-2 text-xs text-muted"><x-icon name="lock" class="mt-0.5 size-3.5" />{{ __('claims.proof_hint') }}</p>
        </aside>
    </div>
</x-layouts.account>
