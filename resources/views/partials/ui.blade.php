{{-- Toasts --}}
<div aria-live="polite" class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4" x-data>
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition.opacity.duration.200ms class="pointer-events-auto flex items-center gap-3 rounded-xl bg-navy-900 px-4 py-3 text-sm font-medium text-white shadow-lift ring-1 ring-gold-400/20">
            <svg viewBox="0 0 24 24" class="size-5 text-gold-300" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <span x-text="t.message"></span>
        </div>
    </template>
</div>

{{-- Confirmation dialog (used by forms with data-confirm) --}}
<div x-data x-show="$store.confirm.open" x-cloak class="fixed inset-0 z-[80] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="confirm-title" @keydown.escape.window="$store.confirm.open = false">
    <div x-show="$store.confirm.open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="$store.confirm.open = false"></div>
    <div x-show="$store.confirm.open" x-transition x-trap.noscroll="$store.confirm.open" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-lift">
        <div class="flex items-start gap-4">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full" :class="$store.confirm.tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-gold-50 text-gold-700'">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </span>
            <div class="min-w-0">
                <h2 id="confirm-title" class="text-base font-semibold text-navy-900" x-text="$store.confirm.title || @js(__('common.confirm_title'))"></h2>
                <p class="mt-1.5 text-sm text-muted-strong" x-text="$store.confirm.message"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-outline" @click="$store.confirm.open = false">{{ __('common.cancel') }}</button>
            <button type="button" class="btn" :class="$store.confirm.tone === 'danger' ? 'btn-danger' : 'btn-primary'" @click="$store.confirm.accept()" x-text="$store.confirm.confirmLabel || @js(__('common.confirm_action'))"></button>
        </div>
    </div>
</div>
