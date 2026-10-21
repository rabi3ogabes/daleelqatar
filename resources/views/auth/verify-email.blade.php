<x-layouts.auth :title="__('auth_ui.verify_title')">
    <div class="card card-pad text-center">
        <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-gold-50 text-gold-700 ring-1 ring-gold-100"><x-icon name="mail-open" class="size-8" :stroke="1.5" /></span>
        <p class="mt-5 text-sm leading-7 text-charcoal">{{ __('auth_ui.verify_subtitle', ['email' => auth()->user()->email]) }}</p>
        <p class="mt-3 text-xs text-muted-strong">{{ __('auth_ui.verify_help') }}</p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button type="submit" class="btn btn-primary w-full"><x-icon name="refresh-cw" class="size-4" />{{ __('auth_ui.verify_resend') }}</button>
        </form>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-muted-strong hover:text-navy-900">{{ __('auth_ui.use_other_account') }}</button>
    </form>
</x-layouts.auth>
