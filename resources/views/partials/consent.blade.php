{{-- Cookie consent (Google Consent Mode v2). The choice is kept in this browser only. --}}
<div x-data="{ show: false, init() { try { this.show = ! localStorage.getItem('dq-consent') } catch (e) {} }, choose(v) { try { localStorage.setItem('dq-consent', v) } catch (e) {} const g = v === 'granted' ? 'granted' : 'denied'; if (window.gtag) gtag('consent', 'update', { ad_storage: g, ad_user_data: g, ad_personalization: g, analytics_storage: g }); this.show = false } }"
    @click.window="if ($event.target.closest('[data-consent-open]')) show = true"
    x-show="show" x-cloak x-transition.opacity role="dialog" aria-live="polite" aria-label="{{ __('directory.consent.title') }}"
    class="fixed inset-x-3 bottom-3 z-[80] mx-auto max-w-3xl rounded-2xl border border-navy-100 bg-white/95 p-4 shadow-lift backdrop-blur sm:inset-x-6 sm:bottom-6 sm:p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <span class="hidden size-11 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300 sm:flex"><x-icon name="shield-check" class="size-5" /></span>
        <p class="flex-1 text-sm leading-relaxed text-navy-800">
            {{ __('directory.consent.text') }}
            <a href="{{ route('cookies') }}" class="font-semibold text-gold-700 underline-offset-2 hover:underline">{{ __('directory.consent.policy') }}</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="btn btn-ghost btn-sm" @click="choose('denied')">{{ __('directory.consent.decline') }}</button>
            <button type="button" class="btn btn-primary btn-sm" @click="choose('granted')">{{ __('directory.consent.accept') }}</button>
        </div>
    </div>
</div>
