<x-layouts.dashboard area="admin" :title="__('admin.claims.show_title', ['business' => $claim->business?->t('name')])" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.claims.title'), 'url' => route('admin.claims.index')], ['label' => $claim->business?->t('name')]]">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-dash.panel :title="__('admin.claims.claimant')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-muted-strong">{{ __('claims.claimant_name') }}</dt><dd class="font-medium text-navy-900">{{ $claim->claimant_name }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('claims.position') }}</dt><dd class="text-navy-900">{{ $claim->position ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('claims.phone') }}</dt><dd class="text-navy-900" dir="ltr">{{ $claim->phone }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('claims.email') }}</dt><dd class="text-navy-900" dir="ltr">{{ $claim->email }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.claims.account') }}</dt><dd class="text-navy-900">{{ $claim->user?->name }} <span class="text-muted-strong" dir="ltr">({{ $claim->user?->email }})</span></dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('common.date') }}</dt><dd class="text-navy-900">{{ format_date($claim->created_at) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-strong">{{ __('claims.message') }}</dt><dd class="whitespace-pre-line text-navy-900">{{ $claim->message ?? '—' }}</dd></div>
                </dl>
                <div class="mt-5 flex items-center gap-3 rounded-xl border border-navy-100 bg-navy-50/40 p-4">
                    <x-icon name="lock" class="size-5 text-gold-600" />
                    <div class="flex-1 text-sm">
                        <p class="font-medium text-navy-900">{{ __('claims.proof') }}</p>
                        <p class="text-xs text-muted-strong">{{ __('admin.private_document_hint') }}</p>
                    </div>
                    @if($claim->proof_path)
                        <a href="{{ route('admin.claims.proof', $claim->id) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><x-icon name="eye" class="size-4" />{{ __('admin.open_document') }}</a>
                    @else
                        <span class="text-xs text-muted">{{ __('admin.no_document') }}</span>
                    @endif
                </div>
            </x-dash.panel>
            <x-dash.panel :title="__('admin.businesses.business')">
                <div class="flex items-center gap-4">
                    <x-business-cover :business="$claim->business" class="size-16 shrink-0 rounded-xl" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-navy-900">{{ $claim->business?->t('name') }}</p>
                        <p class="text-sm text-muted-strong">{{ $claim->business?->category?->t('name') }} · {{ $claim->business?->locationLabel() }}</p>
                        <p class="mt-1 text-xs text-muted-strong">{{ __('admin.businesses.owner') }}: {{ $claim->business?->owner?->name ?? __('admin.businesses.no_owner') }}</p>
                    </div>
                    <a href="{{ route('admin.businesses.show', $claim->business_id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>
                </div>
            </x-dash.panel>
        </div>
        <aside>
            <x-dash.panel :title="__('admin.decision')">
                <div class="mb-4 flex items-center justify-between"><span class="text-sm text-muted-strong">{{ __('common.status') }}</span><x-status-badge :status="$claim->status" /></div>
                @if($claim->decision_note)<p class="mb-4 rounded-lg bg-navy-50 p-3 text-sm text-navy-900">{{ $claim->decision_note }} <span class="block text-xs text-muted-strong">{{ $claim->reviewer?->name }} · {{ format_date($claim->reviewed_at) }}</span></p>@endif
                @include('admin.partials.decision-form', ['action' => route('admin.claims.decide', $claim->id), 'pending' => $claim->status === \App\Enums\RequestStatus::Pending])
            </x-dash.panel>
        </aside>
    </div>
</x-layouts.dashboard>
