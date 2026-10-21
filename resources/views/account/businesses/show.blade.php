@php
    use App\Enums\BusinessStatus;
    $user = auth()->user();
    $status = $business->status;
    $eventIcons = ['call' => 'phone', 'whatsapp' => 'whatsapp', 'website' => 'globe', 'directions' => 'navigation', 'share' => 'share-2'];
@endphp
<x-layouts.account :title="$business->t('name')" :subtitle="$business->category?->t('name').($business->locationLabel() ? ' · '.$business->locationLabel() : '')"
    :breadcrumbs="[['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => $business->t('name')]]">
    <x-slot:actions>
        <x-status-badge :status="$status" />
        @if($business->isPublished())
            <a href="{{ route('business.show', $business) }}" target="_blank" class="btn btn-outline"><x-icon name="external-link" class="size-4" />{{ __('account.businesses.view_public') }}</a>
        @endif
        @can('update', $business)
            <a href="{{ route('account.businesses.edit', $business->uuid) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4" />{{ __('account.businesses.edit') }}</a>
        @endcan
    </x-slot:actions>

    @if($status === BusinessStatus::Rejected && $business->rejection_reason)
        <div class="mb-6 rounded-2xl border border-danger/20 bg-danger-soft p-4 text-sm text-danger" role="alert">
            <p class="font-semibold">{{ __('account.businesses.rejection_reason') }}</p><p class="mt-1">{{ $business->rejection_reason }}</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Submission checklist --}}
            @if(in_array($status, [BusinessStatus::Draft, BusinessStatus::Rejected], true))
                <x-dash.panel :title="__('account.businesses.checklist')">
                    @if($missing === [])
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="flex items-center gap-2 text-sm font-medium text-success"><x-icon name="circle-check" class="size-5" />{{ __('account.businesses.ready') }}</p>
                            <form method="POST" action="{{ route('account.businesses.submit', $business->uuid) }}">@csrf
                                <button class="btn btn-gold"><x-icon name="send" class="size-4" />{{ __('account.businesses.submit') }}</button>
                            </form>
                        </div>
                    @else
                        <ul class="space-y-2">
                            @foreach($missing as $item)
                                <li class="flex items-center gap-2 text-sm text-charcoal"><span class="flex size-5 items-center justify-center rounded-full border border-navy-200 text-muted"><x-icon name="x" class="size-3" /></span>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('account.businesses.edit', $business->uuid) }}" class="btn btn-primary mt-5"><x-icon name="pencil" class="size-4" />{{ __('account.next.'.$status->value) }}</a>
                    @endif
                </x-dash.panel>
            @endif

            {{-- Performance --}}
            <x-dash.panel :title="__('account.businesses.performance')" :subtitle="__('account.dashboard.last_30')">
                @if($business->isPublished())
                    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach([
                            [__('account.businesses.views_30'), $views30, 'eye'],
                            [__('account.businesses.interactions_30'), $interactions->sum(), 'mouse-pointer-click'],
                            [__('account.businesses.favorites'), $business->favorites_count, 'heart'],
                            [__('account.businesses.rating'), $business->reviews_count ? number_format($business->rating_avg, 1).' ★' : '—', 'star'],
                        ] as [$label, $value, $icon])
                            <div class="rounded-xl border border-navy-100 p-3">
                                <p class="flex items-center gap-1.5 text-xs text-muted-strong"><x-icon :name="$icon" class="size-3.5 text-gold-600" />{{ $label }}</p>
                                <p class="mt-1.5 text-xl font-bold text-navy-900 tabular-nums">{{ is_numeric($value) ? format_number($value) : $value }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if($views30 > 0)
                        <x-chart.line :labels="$chartLabels" :values="$chartValues" :title="__('account.businesses.views_30')" />
                    @else
                        <p class="py-6 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</p>
                    @endif
                    <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-5">
                        @foreach($interactions as $type => $count)
                            <div class="flex items-center gap-2 rounded-xl bg-navy-50/70 px-3 py-2">
                                <x-icon :name="$eventIcons[$type] ?? 'activity'" class="size-4 text-gold-700" />
                                <span class="min-w-0 flex-1 truncate text-xs text-muted-strong">{{ __('status.event.'.$type) }}</span>
                                <span class="text-sm font-semibold text-navy-900 tabular-nums">{{ format_number($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-6 text-center text-sm text-muted-strong">{{ __('account.businesses.stats_unpublished') }}</p>
                @endif
            </x-dash.panel>

            {{-- Status history --}}
            <x-dash.panel :title="__('account.businesses.history')">
                <ol class="relative space-y-5 border-s border-navy-100 ps-6">
                    @foreach($business->statusChanges as $change)
                        <li class="relative">
                            <span class="absolute -start-[1.85rem] top-1 flex size-3.5 items-center justify-center rounded-full bg-white ring-2 ring-gold-400"></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$change->to_status" />
                                <span class="text-xs text-muted">{{ format_date($change->created_at, 'D MMM YYYY, h:mm A') }}</span>
                            </div>
                            @if($change->note)<p class="mt-1.5 text-sm text-muted-strong">{{ $change->note }}</p>@endif
                        </li>
                    @endforeach
                    <li class="relative">
                        <span class="absolute -start-[1.85rem] top-1 size-3.5 rounded-full bg-navy-200"></span>
                        <p class="text-sm text-charcoal">{{ __('account.businesses.created_event') }}</p>
                        <span class="text-xs text-muted">{{ format_date($business->created_at, 'D MMM YYYY, h:mm A') }}</span>
                    </li>
                </ol>
            </x-dash.panel>
        </div>

        <div class="space-y-6">
            <x-dash.panel :padding="false">
                <x-business-cover :business="$business" class="aspect-[16/9]" />
                <div class="p-5">
                    <div class="flex items-center justify-between text-xs"><span class="text-muted-strong">{{ __('account.businesses.completeness') }}</span><span class="font-semibold text-navy-900">{{ $completeness }}%</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ $completeness >= 80 ? 'bg-success' : 'bg-gold-500' }}" style="width: {{ $completeness }}%"></div></div>
                    @if($business->t('tagline'))<p class="mt-4 text-sm text-muted-strong">{{ $business->t('tagline') }}</p>@endif
                    @if($business->is_demo)<p class="mt-3 badge badge-muted">{{ __('account.businesses.sample_note') }}</p>@endif
                </div>
            </x-dash.panel>

            {{-- Verification --}}
            <section class="relative overflow-hidden rounded-2xl bg-navy-900 p-5 text-white">
                <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
                <div class="relative">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-gold-400/15 text-gold-300 ring-1 ring-gold-400/30"><x-icon name="badge-check" class="size-5" /></span>
                    @if($business->is_verified)
                        <p class="mt-4 font-semibold">{{ __('account.businesses.verified_note') }}</p>
                    @elseif($pendingVerification)
                        <p class="mt-4 font-semibold">{{ __('account.businesses.verification_title') }}</p>
                        <p class="mt-1 text-sm text-white/70">{{ __('account.businesses.verification_pending') }}</p>
                    @else
                        <p class="mt-4 font-semibold">{{ __('account.businesses.verification_title') }}</p>
                        <p class="mt-1 text-sm text-white/70">{{ __('account.businesses.verification_text') }}</p>
                        @can('requestVerification', $business)
                            <a href="{{ route('account.businesses.verification', $business->uuid) }}" class="btn btn-gold btn-sm mt-4">{{ __('account.businesses.verification_cta') }}</a>
                        @endcan
                    @endif
                </div>
            </section>

            @if($business->isPublished())
                <a href="{{ route('account.offers.create', ['business' => $business->uuid]) }}" class="card card-hover flex items-center gap-3 p-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-gold-50 text-gold-700"><x-icon name="tag" class="size-5" /></span>
                    <span class="flex-1 text-sm font-semibold text-navy-900">{{ __('account.offers.create') }}</span>
                    <x-icon name="chevron-right" class="size-4 text-muted" />
                </a>
            @endif

            {{-- Lifecycle actions --}}
            <x-dash.panel>
                <div class="flex flex-col gap-2">
                    @can('archive', $business)
                        <form method="POST" action="{{ route('account.businesses.archive', $business->uuid) }}" data-confirm="{{ __('account.businesses.archive_confirm') }}" data-confirm-tone="neutral">@csrf
                            <button class="btn btn-outline w-full"><x-icon name="archive" class="size-4" />{{ __('account.businesses.archive') }}</button>
                        </form>
                    @endcan
                    @can('unarchive', $business)
                        <form method="POST" action="{{ route('account.businesses.unarchive', $business->uuid) }}">@csrf
                            <button class="btn btn-outline w-full"><x-icon name="rotate-ccw" class="size-4" />{{ __('account.businesses.unarchive') }}</button>
                        </form>
                    @endcan
                    @can('delete', $business)
                        <form method="POST" action="{{ route('account.businesses.destroy', $business->uuid) }}" data-confirm="{{ __('account.businesses.delete_confirm') }}">@csrf @method('DELETE')
                            <button class="btn btn-danger-outline w-full"><x-icon name="trash-2" class="size-4" />{{ __('account.businesses.delete') }}</button>
                        </form>
                    @endcan
                </div>
            </x-dash.panel>
        </div>
    </div>
</x-layouts.account>
