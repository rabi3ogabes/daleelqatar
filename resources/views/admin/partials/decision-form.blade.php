{{-- Approve / reject decision panel. Expects: $action (url), $pending (bool) --}}
@if($pending)
    <div x-data="{ reject: false }" class="space-y-3">
        <form method="POST" action="{{ $action }}" class="space-y-3">
            @csrf
            <input type="hidden" name="decision" value="approve">
            <x-form.textarea name="note" rows="2" :label="__('admin.decision_note')" :hint="__('admin.decision_note_hint')" />
            <button class="btn btn-primary w-full"><x-icon name="circle-check" class="size-4" />{{ __('admin.approve') }}</button>
        </form>
        <button type="button" class="btn btn-danger-outline w-full" @click="reject = !reject"><x-icon name="circle-x" class="size-4" />{{ __('admin.reject') }}</button>
        <form x-show="reject" x-collapse x-cloak method="POST" action="{{ $action }}" class="space-y-3 rounded-xl border border-danger/15 bg-danger-soft/40 p-3">
            @csrf
            <input type="hidden" name="decision" value="reject">
            <label class="label" for="reject-note">{{ __('admin.reject_reason') }}</label>
            <textarea id="reject-note" name="note" rows="3" required maxlength="2000" class="textarea min-h-20"></textarea>
            <button class="btn btn-danger btn-sm w-full">{{ __('admin.confirm_reject') }}</button>
        </form>
    </div>
@else
    <p class="text-sm text-muted-strong">{{ __('admin.already_decided') }}</p>
@endif
