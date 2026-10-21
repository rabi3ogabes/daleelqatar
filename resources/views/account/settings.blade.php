@php $prefs = $user->notification_preferences ?? []; @endphp
<x-layouts.account :title="__('account.settings.title')" :subtitle="__('account.settings.subtitle')" :breadcrumbs="[['label' => __('account.settings.title')]]">
    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Profile --}}
        <section class="card p-5 sm:p-6" aria-labelledby="s-profile">
            <h2 id="s-profile" class="text-base font-semibold text-navy-900">{{ __('account.settings.profile') }}</h2>
            <p class="mb-5 text-sm text-muted-strong">{{ __('account.settings.profile_hint') }}</p>
            <form method="POST" action="{{ route('account.settings.profile') }}" enctype="multipart/form-data" class="space-y-5" novalidate>
                @csrf @method('PUT')
                <x-form.image name="avatar" :label="__('account.settings.avatar')" :current="$user->avatarUrl()" shape="square">
                    @if($user->avatar_path)
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-danger"><input type="checkbox" name="remove_avatar" value="1" class="checkbox">{{ __('account.settings.remove_avatar') }}</label>
                    @endif
                </x-form.image>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="name" :label="__('auth_ui.name')" :value="$user->name" required maxlength="120" autocomplete="name" />
                    <x-form.input name="email" type="email" dir="ltr" :label="__('auth_ui.email')" :value="$user->email" required autocomplete="email" />
                    <x-form.input name="phone" type="tel" dir="ltr" :label="__('auth_ui.phone')" :value="$user->phone" autocomplete="tel" />
                    <x-form.select name="locale" :label="__('account.settings.language')" :options="['ar' => 'العربية', 'en' => 'English']" :value="$user->locale" required />
                </div>
                <button type="submit" class="btn btn-primary">{{ __('common.save_changes') }}</button>
            </form>
        </section>

        {{-- Password --}}
        <section class="card p-5 sm:p-6" aria-labelledby="s-password">
            <h2 id="s-password" class="text-base font-semibold text-navy-900">{{ __('account.settings.password') }}</h2>
            <p class="mb-5 text-sm text-muted-strong">{{ __('account.settings.password_hint') }}</p>
            <form method="POST" action="{{ route('account.settings.password') }}" class="space-y-5" novalidate>
                @csrf @method('PUT')
                <x-form.password name="current_password" :label="__('account.settings.current_password')" class="sm:max-w-sm" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.password :label="__('account.settings.new_password')" autocomplete="new-password" :hint="__('auth_ui.password_hint')" />
                    <x-form.password name="password_confirmation" :label="__('account.settings.confirm_password')" autocomplete="new-password" />
                </div>
                <button type="submit" class="btn btn-primary">{{ __('account.settings.update_password') }}</button>
            </form>
        </section>

        {{-- Notifications --}}
        <section class="card p-5 sm:p-6" aria-labelledby="s-prefs">
            <h2 id="s-prefs" class="text-base font-semibold text-navy-900">{{ __('account.settings.preferences') }}</h2>
            <p class="mb-5 text-sm text-muted-strong">{{ __('account.settings.preferences_hint') }}</p>
            <form method="POST" action="{{ route('account.settings.preferences') }}" class="space-y-5">
                @csrf @method('PUT')
                <div class="divide-y divide-navy-50 rounded-xl border border-navy-100">
                    @foreach($preferences as $key)
                        <x-form.toggle :name="'prefs['.$key.']'" :label="__('account.settings.pref_'.$key)" :hint="__('account.settings.pref_'.$key.'_hint')" :checked="$prefs[$key] ?? true" class="p-4" />
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
            </form>
        </section>

        {{-- Danger zone --}}
        <section class="rounded-2xl border border-danger/25 bg-white p-5 sm:p-6" aria-labelledby="s-danger" x-data="{ open: {{ $errors->deleteAccount->any() ? 'true' : 'false' }} }">
            <h2 id="s-danger" class="text-base font-semibold text-danger">{{ __('account.settings.danger') }}</h2>
            <p class="mt-1 text-sm text-muted-strong">{{ __('account.settings.danger_hint') }}</p>
            <button type="button" class="btn btn-danger-outline mt-4" x-show="!open" @click="open = true">{{ __('account.settings.delete_button') }}</button>
            <form method="POST" action="{{ route('account.settings.destroy') }}" x-show="open" x-cloak class="mt-4 space-y-4" data-confirm="{{ __('account.settings.delete_confirm') }}">
                @csrf @method('DELETE')
                <div class="sm:max-w-sm">
                    <label for="delete_password" class="label">{{ __('account.settings.confirm_with_password') }}</label>
                    <input id="delete_password" type="password" name="delete_password" required autocomplete="current-password" dir="ltr" class="input @if($errors->deleteAccount->has('delete_password')) input-invalid @endif">
                    @if($errors->deleteAccount->has('delete_password'))<p class="error">{{ $errors->deleteAccount->first('delete_password') }}</p>@endif
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-danger">{{ __('account.settings.delete_button') }}</button>
                    <button type="button" class="btn btn-ghost" @click="open = false">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.account>
