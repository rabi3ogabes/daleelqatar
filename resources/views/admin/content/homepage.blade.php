<x-layouts.dashboard area="admin" :title="__('admin.content.homepage')" :subtitle="__('admin.content.homepage_hint')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.content.homepage')]]">
    <x-slot:actions><a href="{{ route('home') }}" target="_blank" class="btn btn-outline"><x-icon name="external-link" class="size-4" />{{ __('admin.content.preview') }}</a></x-slot:actions>
    <form method="POST" action="{{ route('admin.homepage.update') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf @method('PUT')
        <div class="space-y-6">
            <x-dash.panel :title="__('admin.content.hero')">
                <div class="relative mb-6 overflow-hidden rounded-2xl bg-navy-950">
                    <img src="{{ $heroImage }}" alt="" class="h-48 w-full object-cover opacity-80">
                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-navy-950/40 p-4 text-center text-white">
                        <p class="text-xl font-bold">{{ $heroTitle[app()->getLocale()] ?? '' }}</p>
                        <p class="mt-1 text-sm text-white/75">{{ $heroSubtitle[app()->getLocale()] ?? '' }}</p>
                    </div>
                </div>
                <div class="grid gap-5">
                    <fieldset>
                        <legend class="label">{{ __('admin.content.hero_title') }} <span class="text-danger">*</span></legend>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input name="hero_title[ar]" dir="rtl" lang="ar" maxlength="160" required value="{{ old('hero_title.ar', $heroTitle['ar'] ?? '') }}" class="input" aria-label="{{ __('admin.content.hero_title') }} — {{ __('common.arabic') }}">
                            <input name="hero_title[en]" dir="ltr" lang="en" maxlength="160" required value="{{ old('hero_title.en', $heroTitle['en'] ?? '') }}" class="input" aria-label="{{ __('admin.content.hero_title') }} — {{ __('common.english') }}">
                        </div>
                        @error('hero_title.*')<p class="error">{{ $message }}</p>@enderror
                    </fieldset>
                    <fieldset>
                        <legend class="label">{{ __('admin.content.hero_subtitle') }}</legend>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input name="hero_subtitle[ar]" dir="rtl" lang="ar" maxlength="300" value="{{ old('hero_subtitle.ar', $heroSubtitle['ar'] ?? '') }}" class="input" aria-label="{{ __('admin.content.hero_subtitle') }} — {{ __('common.arabic') }}">
                            <input name="hero_subtitle[en]" dir="ltr" lang="en" maxlength="300" value="{{ old('hero_subtitle.en', $heroSubtitle['en'] ?? '') }}" class="input" aria-label="{{ __('admin.content.hero_subtitle') }} — {{ __('common.english') }}">
                        </div>
                    </fieldset>
                    <x-form.image name="hero_image" :label="__('admin.content.hero_image')" :hint="__('admin.content.hero_image_hint')" />
                    @if($customImage)<x-form.checkbox name="reset_image" :label="__('admin.content.reset_image')" />@endif
                </div>
            </x-dash.panel>
        </div>
        <aside>
            <div class="card space-y-5 p-5 xl:sticky xl:top-24">
                <x-form.toggle name="show_offers" :label="__('admin.content.show_offers')" :checked="$showOffers" />
                <x-form.toggle name="show_trust" :label="__('admin.content.show_trust')" :checked="$showTrust" />
                <p class="text-xs text-muted-strong">{{ __('admin.content.homepage_sections_hint') }}</p>
                <button class="btn btn-primary w-full"><x-icon name="check" class="size-4" />{{ __('common.save_changes') }}</button>
            </div>
        </aside>
    </form>
</x-layouts.dashboard>
