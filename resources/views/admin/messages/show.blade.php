<x-layouts.dashboard area="admin" :title="$message->subject" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.messages.title'), 'url' => route('admin.messages.index')], ['label' => \Illuminate\Support\Str::limit($message->subject, 40)]]">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <x-dash.panel>
            <dl class="mb-5 grid gap-4 border-b border-navy-50 pb-5 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-muted-strong">{{ __('fields.name') }}</dt><dd class="font-medium text-navy-900">{{ $message->name }}</dd></div>
                <div><dt class="text-xs text-muted-strong">{{ __('fields.email') }}</dt><dd dir="ltr"><a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}" class="link">{{ $message->email }}</a></dd></div>
                <div><dt class="text-xs text-muted-strong">{{ __('fields.phone') }}</dt><dd class="text-navy-900" dir="ltr">{{ $message->phone ?? '—' }}</dd></div>
                <div><dt class="text-xs text-muted-strong">{{ __('common.date') }}</dt><dd class="text-navy-900">{{ $message->created_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm') }}</dd></div>
                <div><dt class="text-xs text-muted-strong">{{ __('admin.users.language') }}</dt><dd class="text-navy-900">{{ $message->locale === 'en' ? __('common.english') : __('common.arabic') }}</dd></div>
                <div><dt class="text-xs text-muted-strong">{{ __('admin.messages.account') }}</dt><dd class="text-navy-900">{{ $message->user?->name ?? __('admin.messages.guest') }}</dd></div>
            </dl>
            <p class="text-sm leading-8 whitespace-pre-line text-charcoal" dir="auto">{{ $message->message }}</p>
        </x-dash.panel>
        <aside class="space-y-6">
            <x-dash.panel :title="__('admin.messages.handling')">
                <form method="POST" action="{{ route('admin.messages.update', $message) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-form.select name="status" :label="__('common.status')" :options="collect(\App\Http\Controllers\Admin\MessageController::STATUSES)->mapWithKeys(fn ($s) => [$s => __('admin.messages.statuses.'.$s)])->all()" :value="$message->status" />
                    <x-form.textarea name="internal_note" rows="4" :label="__('admin.messages.internal_note')" :value="$message->internal_note" :hint="__('admin.messages.internal_note_hint')" />
                    <button class="btn btn-primary w-full">{{ __('common.save') }}</button>
                </form>
                <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}" class="btn btn-outline mt-3 w-full"><x-icon name="mail" class="size-4" />{{ __('admin.messages.reply') }}</a>
                @if($message->handler)<p class="mt-3 text-xs text-muted-strong">{{ __('admin.messages.handled_by', ['name' => $message->handler->name, 'date' => format_date($message->handled_at)]) }}</p>@endif
            </x-dash.panel>
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="{{ __('admin.messages.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}" class="card p-5">
                @csrf @method('DELETE')
                <button class="btn btn-danger-outline w-full"><x-icon name="trash-2" class="size-4" />{{ __('common.delete') }}</button>
            </form>
        </aside>
    </div>
</x-layouts.dashboard>
