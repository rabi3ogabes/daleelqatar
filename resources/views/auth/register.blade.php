<x-layouts.auth :title="__('auth_ui.register_heading')" :subtitle="__('auth_ui.register_subtitle')">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-form.input name="name" icon="user" :label="__('auth_ui.name')" autocomplete="name" autofocus required maxlength="120" />
        <x-form.input name="email" type="email" icon="at-sign" dir="ltr" :label="__('auth_ui.email')" autocomplete="email" required />
        <x-form.input name="phone" type="tel" icon="smartphone" dir="ltr" :label="__('auth_ui.phone').' ('.__('common.optional').')'" autocomplete="tel" placeholder="+974" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.password :label="__('auth_ui.password')" autocomplete="new-password" />
            <x-form.password name="password_confirmation" :label="__('auth_ui.password_confirmation')" autocomplete="new-password" />
        </div>
        <p class="-mt-2 text-xs text-muted-strong">{{ __('auth_ui.password_hint') }}</p>
        {{-- Honeypot: hidden from people, irresistible to bots --}}
        <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>
        <div>
            <label class="flex items-start gap-2.5 text-sm text-charcoal">
                <input type="checkbox" name="terms" value="1" class="checkbox mt-1" @checked(old('terms')) required>
                <span>{!! __('auth_ui.accept_terms', [
                    'terms' => '<a href="'.e(route('terms')).'" target="_blank" class="link">'.e(__('auth_ui.terms_link')).'</a>',
                    'privacy' => '<a href="'.e(route('privacy')).'" target="_blank" class="link">'.e(__('auth_ui.privacy_link')).'</a>',
                ]) !!}</span>
            </label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-gold btn-lg w-full">{{ __('auth_ui.sign_up') }}<x-icon name="arrow-right" class="size-5" /></button>
    </form>
    <p class="mt-8 text-center text-sm text-muted-strong">{{ __('auth_ui.have_account') }} <a href="{{ route('login') }}" class="link">{{ __('auth_ui.sign_in_instead') }}</a></p>
</x-layouts.auth>
