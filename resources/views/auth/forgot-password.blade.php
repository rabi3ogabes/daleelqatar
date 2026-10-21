<x-layouts.auth :title="__('auth_ui.forgot_title')" :subtitle="__('auth_ui.forgot_subtitle')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
        @csrf
        <x-form.input name="email" type="email" icon="at-sign" dir="ltr" :label="__('auth_ui.email')" autocomplete="email" autofocus required />
        <button type="submit" class="btn btn-primary btn-lg w-full"><x-icon name="send" class="size-5" />{{ __('auth_ui.send_link') }}</button>
    </form>
    <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-1.5 text-sm font-medium text-gold-700 hover:text-gold-800"><x-icon name="arrow-left" class="size-4" />{{ __('auth_ui.back_to_login') }}</a>
</x-layouts.auth>
