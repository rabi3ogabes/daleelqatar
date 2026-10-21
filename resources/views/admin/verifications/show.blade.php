@php $v = $verification; @endphp
<x-layouts.dashboard area="admin" :title="__('admin.verifications.show_title', ['business' => $v->business?->t('name')])" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.verifications.title'), 'url' => route('admin.verifications.index')], ['label' => $v->business?->t('name')]]">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-dash.panel :title="__('admin.verifications.details')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.verifications.requested_by') }}</dt><dd class="font-medium text-navy-900">{{ $v->user?->name }} <span class="text-muted-strong" dir="ltr">({{ $v->user?->email }})</span></dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.verifications.cr') }}</dt><dd class="text-navy-900" dir="ltr">{{ $v->commercial_registration ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('common.date') }}</dt><dd class="text-navy-900">{{ format_date($v->created_at) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-strong">{{ __('admin.verifications.notes') }}</dt><dd class="whitespace-pre-line text-navy-900">{{ $v->notes ?? '—' }}</dd></div>
                </dl>
                <div class="mt-5 flex items-center gap-3 rounded-xl border border-navy-100 bg-navy-50/40 p-4">
                    <x-icon name="lock" class="size-5 text-gold-600" />
                    <div class="flex-1 text-sm"><p class="font-medium text-navy-900">{{ __('admin.verifications.document') }}</p><p class="text-xs text-muted-strong">{{ __('admin.private_document_hint') }}</p></div>
                    @if($v->document_path)
                        <a href="{{ route('admin.verifications.document', $v->id) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><x-icon name="eye" class="size-4" />{{ __('admin.open_document') }}</a>
                    @else
                        <span class="text-xs text-muted">{{ __('admin.no_document') }}</span>
                    @endif
                </div>
            </x-dash.panel>
            <x-dash.panel :title="__('admin.businesses.business')">
                <div class="flex items-center gap-4">
                    <x-business-cover :business="$v->business" class="size-16 shrink-0 rounded-xl" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-navy-900">{{ $v->business?->t('name') }} @if($v->business?->is_verified)<span class="badge badge-verified ms-1">{{ __('business.verified') }}</span>@endif</p>
                        <p class="text-sm text-muted-strong">{{ $v->business?->category?->t('name') }} · {{ $v->business?->locationLabel() }}</p>
                    </div>
                    <a href="{{ route('admin.businesses.show', $v->business_id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>
                </div>
            </x-dash.panel>
        </div>
        <aside>
            <x-dash.panel :title="__('admin.decision')">
                <div class="mb-4 flex items-center justify-between"><span class="text-sm text-muted-strong">{{ __('common.status') }}</span><x-status-badge :status="$v->status" /></div>
                @if($v->decision_note)<p class="mb-4 rounded-lg bg-navy-50 p-3 text-sm text-navy-900">{{ $v->decision_note }} <span class="block text-xs text-muted-strong">{{ $v->reviewer?->name }} · {{ format_date($v->reviewed_at) }}</span></p>@endif
                @include('admin.partials.decision-form', ['action' => route('admin.verifications.decide', $v->id), 'pending' => $v->status === \App\Enums\RequestStatus::Pending])
            </x-dash.panel>
        </aside>
    </div>
</x-layouts.dashboard>
