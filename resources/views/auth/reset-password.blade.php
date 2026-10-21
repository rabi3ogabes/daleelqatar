<x-layouts.auth :title="__('auth_ui.reset_title')" :subtitle="__('auth_ui.reset_subtitle')">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" type="email" icon="at-sign" dir="ltr" :label="__('auth_ui.email')" :value="$email" autocomplete="email" required />
        <x-form.password :label="__('auth_ui.password')" autocomplete="new-password" :hint="__('auth_ui.password_hint')" />
        <x-form.password name="password_confirmation" :label="__('auth_ui.password_confirmation')" autocomplete="new-password" />
        <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('auth_ui.reset_button') }}</button>
    </form>
</x-layouts.auth>
