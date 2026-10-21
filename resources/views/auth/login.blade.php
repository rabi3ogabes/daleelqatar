<x-layouts.auth :title="__('auth_ui.login_heading')" :subtitle="__('auth_ui.login_subtitle')">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-form.input name="email" type="email" icon="at-sign" dir="ltr" :label="__('auth_ui.email')" autocomplete="email" autofocus required />
        <x-form.password :label="__('auth_ui.password')" />
        <div class="flex items-center justify-between gap-4">
            <x-form.checkbox name="remember" :label="__('auth_ui.remember')" />
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-gold-700 hover:text-gold-800">{{ __('auth_ui.forgot') }}</a>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('auth_ui.sign_in') }}<x-icon name="arrow-right" class="size-5" /></button>
    </form>
    <div class="my-8 flex items-center gap-4 text-xs text-muted"><span class="h-px flex-1 bg-navy-100"></span>{{ __('auth_ui.no_account') }}<span class="h-px flex-1 bg-navy-100"></span></div>
    <a href="{{ route('register') }}" class="btn btn-outline btn-lg w-full"><x-icon name="user-plus" class="size-5" />{{ __('auth_ui.create_one') }}</a>
</x-layouts.auth>
