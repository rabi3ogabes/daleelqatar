<x-layouts.app>
    <x-page-hero :title="__('claims.title', ['business' => $business->t('name')])" :subtitle="__('claims.intro')" icon="key-round"
        :breadcrumbs="[['label' => $business->t('name'), 'url' => route('business.show', $business)], ['label' => __('business.claim_cta')]]" />
    <div class="container-app grid gap-8 py-12 lg:grid-cols-12">
        <form method="POST" action="{{ route('business.claim.store', $business) }}" enctype="multipart/form-data" class="card card-pad space-y-5 lg:col-span-8">
            @csrf
            <x-flash />
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input name="claimant_name" :label="__('claims.claimant_name')" :value="auth()->user()->name" required maxlength="120" autocomplete="name" />
                <x-form.input name="position" :label="__('claims.position')" maxlength="120" />
                <x-form.input name="phone" type="tel" dir="ltr" :label="__('claims.phone')" :value="auth()->user()->phone" required autocomplete="tel" />
                <x-form.input name="email" type="email" dir="ltr" :label="__('claims.email')" :value="auth()->user()->email" required autocomplete="email" />
            </div>
            <x-form.textarea name="message" :label="__('claims.message')" rows="4" maxlength="2000" />
            <div>
                <label for="f_proof" class="label">{{ __('claims.proof') }} <span class="font-normal text-muted">({{ __('common.optional') }})</span></label>
                <x-form.file name="proof" id="f_proof" accept="application/pdf,image/jpeg,image/png" />
                <p class="hint">{{ __('claims.proof_hint') }} {{ __('common.document_requirements', ['size' => round((int) setting('uploads.max_document_kb', 8192) / 1024)]) }}</p>
            </div>
            <x-form.checkbox name="terms" :label="__('claims.confirm_owner')" required />
            <button class="btn btn-primary btn-lg"><x-icon name="send" class="size-5" />{{ __('claims.submit') }}</button>
        </form>
        <aside class="lg:col-span-4">
            <div class="card overflow-hidden">
                <x-business-cover :business="$business" class="aspect-[16/9]" />
                <div class="p-5">
                    <p class="text-xs text-gold-700">{{ $business->category?->t('name') }}</p>
                    <p class="mt-1 font-semibold text-navy-900">{{ $business->t('name') }}</p>
                    <p class="mt-1 text-sm text-muted-strong">{{ $business->locationLabel() }}</p>
                </div>
            </div>
            <p class="mt-4 flex items-start gap-2 text-xs text-muted-strong"><x-icon name="shield-check" class="size-4 text-gold-600" />{{ __('claims.proof_hint') }}</p>
        </aside>
    </div>
</x-layouts.app>
