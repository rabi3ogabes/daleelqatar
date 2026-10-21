@php
    use App\Enums\BusinessStatus;
    $s = $business->status;
    // Map allowed workflow transitions to moderation actions.
    $actions = collect($transitions)->map(fn (BusinessStatus $to) => match (true) {
        $to === BusinessStatus::Approved && $s === BusinessStatus::Suspended => 'restore',
        $to === BusinessStatus::Approved => 'approve',
        $to === BusinessStatus::Rejected => 'reject',
        $to === BusinessStatus::Suspended => 'suspend',
        $to === BusinessStatus::Archived => 'archive',
        $to === BusinessStatus::Draft && $s === BusinessStatus::Archived => 'unarchive',
        $to === BusinessStatus::Pending && $s === BusinessStatus::Approved => 'reopen',
        default => null,
    })->filter()->unique()->values();
    $tones = ['approve' => 'btn-primary', 'restore' => 'btn-primary', 'reject' => 'btn-danger-outline', 'suspend' => 'btn-danger-outline', 'archive' => 'btn-outline', 'unarchive' => 'btn-outline', 'reopen' => 'btn-outline'];
    $icons = ['approve' => 'circle-check', 'restore' => 'rotate-ccw', 'reject' => 'circle-x', 'suspend' => 'ban', 'archive' => 'archive', 'unarchive' => 'rotate-ccw', 'reopen' => 'history'];
@endphp
<x-layouts.dashboard area="admin" :title="$business->t('name')" :subtitle="$business->t('name', app()->getLocale() === 'ar' ? 'en' : 'ar')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.businesses.title'), 'url' => route('admin.businesses.index')], ['label' => $business->t('name')]]">
    <x-slot:actions>
        @if($business->isPublished())
            <a href="{{ route('business.show', $business) }}" target="_blank" class="btn btn-outline"><x-icon name="external-link" class="size-4" />{{ __('admin.businesses.view_public') }}</a>
        @endif
        @can('businesses.update')@unless($business->trashed())
            <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4" />{{ __('common.edit') }}</a>
        @endunless @endcan
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Summary --}}
            <section class="card overflow-hidden">
                <div class="relative h-40 bg-navy-900 sm:h-52">
                    <x-business-cover :business="$business" class="absolute inset-0 h-full" />
                    <div class="absolute inset-x-4 bottom-4 flex flex-wrap items-center gap-2">
                        @if($business->trashed())<span class="badge badge-danger">{{ __('admin.businesses.deleted_badge') }}</span>@endif
                        <x-status-badge :status="$business->status" class="bg-white" />
                        @if($business->is_verified)<span class="badge badge-verified"><x-icon name="badge-check" class="size-3" />{{ __('business.verified') }}</span>@endif
                        @if($business->isFeaturedNow())<span class="badge badge-gold">{{ __('business.featured') }}@if($business->featured_until) · {{ format_date($business->featured_until) }}@endif</span>@endif
                        @if($business->is_demo)<span class="badge badge-muted">{{ __('common.sample_listing') }}</span>@endif
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-4 p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs text-muted-strong">{{ __('search.category') }}</dt><dd class="mt-0.5 font-medium text-navy-900">{{ $business->category?->parent ? $business->category->parent->t('name').' › ' : '' }}{{ $business->category?->t('name') }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('business.location') }}</dt><dd class="mt-0.5 font-medium text-navy-900">{{ $business->locationLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.businesses.slug') }}</dt><dd class="mt-0.5 font-mono text-xs text-navy-900" dir="ltr">{{ $business->slug }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('business.call') }}</dt><dd class="mt-0.5 text-navy-900" dir="ltr">{{ $business->phone ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('business.whatsapp') }}</dt><dd class="mt-0.5 text-navy-900" dir="ltr">{{ $business->whatsapp ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('business.email') }}</dt><dd class="mt-0.5 truncate text-navy-900" dir="ltr">{{ $business->email ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-muted-strong">{{ __('business.website') }}</dt><dd class="mt-0.5 truncate" dir="ltr">@if($url = safe_external_url($business->website))<a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow" class="link">{{ $url }}</a>@else — @endif</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.businesses.metrics') }}</dt><dd class="mt-0.5 text-navy-900">{{ format_number($business->views_count) }} {{ __('admin.dashboard.views_unit') }} · {{ format_number($business->favorites_count) }} ♥ · {{ $business->reviews_count ? number_format((float) $business->rating_avg, 1).'★ ('.$business->reviews_count.')' : '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('common.created_at') }}</dt><dd class="mt-0.5 text-navy-900">{{ format_date($business->created_at) }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.businesses.submitted_at') }}</dt><dd class="mt-0.5 text-navy-900">{{ format_date($business->submitted_at) }}</dd></div>
                    <div><dt class="text-xs text-muted-strong">{{ __('admin.businesses.published_at') }}</dt><dd class="mt-0.5 text-navy-900">{{ format_date($business->published_at) }}@if($business->approver) · {{ $business->approver->name }}@endif</dd></div>
                </dl>
                @if($business->rejection_reason)
                    <div class="mx-5 mb-5 rounded-xl border border-danger/15 bg-danger-soft px-4 py-3 text-sm text-danger"><strong>{{ __('admin.businesses.rejection_reason') }}:</strong> {{ $business->rejection_reason }}</div>
                @endif
                @if($missing && in_array($s, [BusinessStatus::Draft, BusinessStatus::Pending, BusinessStatus::Rejected], true))
                    <div class="mx-5 mb-5 rounded-xl border border-warning/20 bg-warning-soft px-4 py-3 text-sm text-warning"><strong>{{ __('admin.businesses.incomplete') }}:</strong> {{ implode('، ', $missing) }}</div>
                @endif
            </section>

            {{-- Descriptions --}}
            <x-dash.panel :title="__('business.about')">
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach(['ar' => 'rtl', 'en' => 'ltr'] as $lang => $dir)
                        <div dir="{{ $dir }}" lang="{{ $lang }}">
                            <p class="eyebrow mb-2">{{ __('common.'.($lang === 'ar' ? 'arabic' : 'english')) }}</p>
                            @if($business->{'tagline_'.$lang})<p class="mb-2 font-medium text-navy-900">{{ $business->{'tagline_'.$lang} }}</p>@endif
                            <p class="text-sm leading-7 whitespace-pre-line text-charcoal">{{ $business->{'description_'.$lang} ?: '—' }}</p>
                        </div>
                    @endforeach
                </div>
                @if($business->services->isNotEmpty())
                    <h3 class="mt-6 mb-2 text-sm font-semibold text-navy-900">{{ __('business.services') }}</h3>
                    <div class="flex flex-wrap gap-2">@foreach($business->services as $svc)<span class="badge badge-neutral">{{ $svc->t('name') }}@if($svc->price_from) · {{ __('common.from_price', ['price' => number_format((float) $svc->price_from).' '.__('common.qar')]) }}@endif</span>@endforeach</div>
                @endif
                @if($business->openingHours->isNotEmpty())
                    <h3 class="mt-6 mb-2 text-sm font-semibold text-navy-900">{{ __('business.hours') }}</h3>
                    <ul class="grid gap-1 text-sm sm:grid-cols-2">
                        @foreach($business->openingHours->groupBy('day_of_week') as $day => $rows)
                            <li class="flex justify-between gap-3 rounded-lg bg-navy-50/50 px-3 py-1.5"><span class="text-muted-strong">{{ __('business.days.'.$day) }}</span><span class="text-navy-900">{{ $rows->map->formatted()->implode(' · ') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-dash.panel>

            {{-- Gallery --}}
            <x-dash.panel :title="__('business_form.gallery')" :subtitle="__('business_form.gallery_hint', ['max' => setting('uploads.max_gallery_images', 12)])">
                @if($business->images->isNotEmpty())
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach($business->images as $image)
                            <li class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-navy-50">
                                <img src="{{ $image->url() }}" alt="" loading="lazy" class="size-full object-cover">
                                @can('businesses.update')
                                    <form method="POST" action="{{ route('admin.businesses.gallery.destroy', [$business->id, $image->id]) }}" data-confirm="{{ __('admin.businesses.confirm_delete_image') }}" class="absolute end-2 top-2">
                                        @csrf @method('DELETE')
                                        <button class="flex size-8 items-center justify-center rounded-lg bg-white/90 text-danger shadow-soft" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-muted-strong">{{ __('business_form.gallery_empty') }}</p>
                @endif
                @can('businesses.update')
                    <form method="POST" action="{{ route('admin.businesses.gallery', $business->id) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                        @csrf
                        <x-form.file name="images[]" id="admin-gallery" multiple accept="image/jpeg,image/png,image/webp" />
                        <button class="btn btn-primary btn-sm"><x-icon name="upload" class="size-4" />{{ __('business_form.gallery_add') }}</button>
                    </form>
                @endcan
            </x-dash.panel>

            {{-- Status history --}}
            <x-dash.panel :title="__('admin.businesses.history')" :padding="false">
                <ol class="divide-y divide-navy-50">
                    @forelse($business->statusChanges as $change)
                        <li class="flex items-start gap-3 px-5 py-3">
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-gold-500"></span>
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="text-navy-900">
                                    @if($change->from_status){{ $change->from_status->label() }} <x-icon name="arrow-right" class="inline size-3.5 text-muted" /> @endif
                                    <strong>{{ $change->to_status->label() }}</strong>
                                    <span class="text-muted-strong">— {{ $change->user?->name ?? __('admin.audit.system') }}</span>
                                </p>
                                @if($change->note)<p class="mt-0.5 text-xs text-muted-strong">{{ $change->note }}</p>@endif
                            </div>
                            <time class="shrink-0 text-xs text-muted" datetime="{{ $change->created_at?->toIso8601String() }}">{{ $change->created_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm') }}</time>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                    @endforelse
                </ol>
            </x-dash.panel>

            {{-- Smart enrichment --}}
            @can('imports.manage')@unless($business->trashed())
                @php $latest = \App\Models\BusinessSuggestion::query()->where('business_id', $business->id)->latest('id')->first(); @endphp
                <x-dash.panel :title="__('enrichment.panel.title')" :subtitle="__('enrichment.panel.subtitle')">
                    <x-slot:actions>
                        <form method="POST" action="{{ route('admin.enrichment.business', $business->id) }}">@csrf<button class="btn btn-primary btn-sm" data-busy><x-icon name="sparkles" class="size-4" />{{ $business->enriched_at ? __('enrichment.recheck') : __('enrichment.panel.run') }}</button></form>
                    </x-slot:actions>
                    <dl class="grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-xl bg-navy-50/60 p-3">
                            <dt class="text-xs text-muted-strong">{{ __('enrichment.panel.google') }}</dt>
                            <dd class="mt-1 font-semibold text-navy-900">
                                @if($business->google_maps_url)<a href="{{ $business->google_maps_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-gold-700 hover:underline"><x-icon name="map-pin" class="size-3.5" />{{ __('enrichment.panel.linked') }}</a>@else{{ __('enrichment.panel.not_linked') }}@endif
                            </dd>
                        </div>
                        <div class="rounded-xl bg-navy-50/60 p-3">
                            <dt class="text-xs text-muted-strong">{{ __('enrichment.panel.website') }}</dt>
                            <dd @class(['mt-1 font-semibold', 'text-warning' => in_array($business->website_status, ['none', 'unreachable'], true), 'text-navy-900' => ! in_array($business->website_status, ['none', 'unreachable'], true)])>{{ __('enrichment.website_status.'.($business->website_status ?? 'unknown')) }}</dd>
                        </div>
                        <div class="rounded-xl bg-navy-50/60 p-3">
                            <dt class="text-xs text-muted-strong">{{ __('enrichment.panel.last_run') }}</dt>
                            <dd class="mt-1 font-semibold text-navy-900">
                                @if($latest)<a href="{{ route('admin.enrichment.show', $latest) }}" class="text-gold-700 hover:underline">{{ __('enrichment.status.'.$latest->status) }}</a><span class="block text-xs font-normal text-muted">{{ $latest->created_at?->diffForHumans() }}</span>@else{{ __('enrichment.panel.never') }}@endif
                            </dd>
                        </div>
                    </dl>
                </x-dash.panel>
            @endunless @endcan

            {{-- Claims, verification, reports --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <x-dash.panel :title="__('admin.claims.title')" :padding="false">
                    <ul class="divide-y divide-navy-50">
                        @forelse($business->claimRequests as $claim)
                            <li class="flex items-center gap-3 px-5 py-3 text-sm">
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium text-navy-900">{{ $claim->claimant_name }}</span><span class="block truncate text-xs text-muted-strong" dir="ltr">{{ $claim->user?->email }}</span></span>
                                <x-status-badge :status="$claim->status" />
                                @can('claims.review')<a href="{{ route('admin.claims.show', $claim->id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>@endcan
                            </li>
                        @empty
                            <li class="px-5 py-6 text-sm text-muted-strong">{{ __('admin.claims.empty') }}</li>
                        @endforelse
                    </ul>
                </x-dash.panel>
                <x-dash.panel :title="__('admin.verifications.title')" :padding="false">
                    <ul class="divide-y divide-navy-50">
                        @forelse($business->verificationRequests as $vr)
                            <li class="flex items-center gap-3 px-5 py-3 text-sm">
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium text-navy-900">{{ $vr->user?->name }}</span><span class="block text-xs text-muted-strong">{{ format_date($vr->created_at) }}</span></span>
                                <x-status-badge :status="$vr->status" />
                                @can('businesses.verify')<a href="{{ route('admin.verifications.show', $vr->id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>@endcan
                            </li>
                        @empty
                            <li class="px-5 py-6 text-sm text-muted-strong">{{ __('admin.verifications.empty') }}</li>
                        @endforelse
                    </ul>
                </x-dash.panel>
            </div>

            @if($business->reports->isNotEmpty())
                <x-dash.panel :title="__('admin.reports.listing_reports')" :padding="false">
                    <ul class="divide-y divide-navy-50">
                        @foreach($business->reports as $report)
                            <li class="flex items-start gap-3 px-5 py-3 text-sm">
                                <span class="min-w-0 flex-1"><span class="font-medium text-navy-900">{{ $report->reason->label() }}</span>@if($report->details)<span class="block text-xs text-muted-strong">{{ $report->details }}</span>@endif</span>
                                <x-status-badge :status="$report->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-dash.panel>
            @endif

            {{-- Record audit trail --}}
            @can('audit.view')
                <x-dash.panel :title="__('admin.audit.record_history')" :padding="false">
                    <ul class="divide-y divide-navy-50">
                        @forelse($audit as $log)
                            <li class="flex items-start gap-3 px-5 py-3 text-sm">
                                <x-icon name="history" class="mt-0.5 size-4 text-gold-600" />
                                <span class="min-w-0 flex-1"><span class="font-medium text-navy-900">{{ \App\Support\AuditLabels::action($log->action) }}</span> <span class="text-muted-strong">— {{ $log->user?->name ?? __('admin.audit.system') }}</span>
                                    @if($log->new_values)<span class="block truncate text-xs text-muted">{{ implode(', ', array_keys($log->new_values)) }}</span>@endif
                                </span>
                                <a href="{{ route('admin.audit.show', $log->id) }}" class="shrink-0 text-xs text-gold-700 hover:underline">{{ $log->created_at?->diffForHumans() }}</a>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                        @endforelse
                    </ul>
                </x-dash.panel>
            @endcan
        </div>

        {{-- Actions --}}
        <aside class="space-y-6">
            @if(! $business->trashed())
                @can('businesses.moderate')
                    <x-dash.panel :title="__('admin.businesses.moderation')">
                        <div class="space-y-2" x-data="{ open: null }">
                            @forelse($actions as $action)
                                @if(in_array($action, ['reject', 'suspend'], true))
                                    <button type="button" class="btn {{ $tones[$action] }} w-full justify-start" @click="open = open === '{{ $action }}' ? null : '{{ $action }}'" :aria-expanded="(open === '{{ $action }}').toString()"><x-icon :name="$icons[$action]" class="size-4" />{{ __('admin.businesses.actions.'.$action) }}</button>
                                    <form x-show="open === '{{ $action }}'" x-collapse x-cloak method="POST" action="{{ route('admin.businesses.status', $business->id) }}" class="space-y-2 rounded-xl border border-navy-100 bg-navy-50/50 p-3">
                                        @csrf <input type="hidden" name="action" value="{{ $action }}">
                                        <label for="reason-{{ $action }}" class="label">{{ __('admin.businesses.reason') }}</label>
                                        <textarea id="reason-{{ $action }}" name="reason" rows="3" required maxlength="2000" class="textarea min-h-20" placeholder="{{ __('admin.businesses.reason_placeholder') }}"></textarea>
                                        <button class="btn btn-danger btn-sm w-full">{{ __('admin.businesses.actions.'.$action) }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.businesses.status', $business->id) }}" @if(in_array($action, ['archive'], true)) data-confirm="{{ __('admin.businesses.confirm_archive') }}" data-confirm-tone="primary" @endif>
                                        @csrf <input type="hidden" name="action" value="{{ $action }}">
                                        <button class="btn {{ $tones[$action] }} w-full justify-start"><x-icon :name="$icons[$action]" class="size-4" />{{ __('admin.businesses.actions.'.$action) }}</button>
                                    </form>
                                @endif
                            @empty
                                <p class="text-sm text-muted-strong">{{ __('admin.businesses.no_actions') }}</p>
                            @endforelse
                        </div>
                    </x-dash.panel>
                @endcan

                @can('businesses.feature')
                    <x-dash.panel :title="__('admin.businesses.featuring')">
                        <form method="POST" action="{{ route('admin.businesses.feature', $business->id) }}" class="space-y-3">
                            @csrf
                            @if($business->isFeaturedNow())
                                <input type="hidden" name="featured" value="0">
                                <p class="text-sm text-muted-strong">{{ __('admin.businesses.is_featured') }}@if($business->featured_until) — {{ __('admin.businesses.until', ['date' => format_date($business->featured_until)]) }}@endif</p>
                                <button class="btn btn-outline w-full"><x-icon name="x" class="size-4" />{{ __('admin.businesses.unfeature') }}</button>
                            @else
                                <input type="hidden" name="featured" value="1">
                                <x-form.input name="featured_until" type="date" :label="__('admin.businesses.featured_until')" :hint="__('admin.businesses.featured_until_hint')" />
                                <button class="btn btn-gold w-full"><x-icon name="sparkles" class="size-4" />{{ __('admin.businesses.feature') }}</button>
                            @endif
                        </form>
                    </x-dash.panel>
                @endcan

                @can('businesses.verify')
                    <x-dash.panel :title="__('admin.businesses.verification')">
                        <form method="POST" action="{{ route('admin.businesses.verify', $business->id) }}" @if($business->is_verified) data-confirm="{{ __('admin.businesses.confirm_unverify') }}" @endif class="space-y-3">
                            @csrf
                            <input type="hidden" name="verified" value="{{ $business->is_verified ? 0 : 1 }}">
                            <p class="text-sm text-muted-strong">{{ $business->is_verified ? __('admin.businesses.verified_since', ['date' => format_date($business->verified_at)]) : __('admin.businesses.verify_hint') }}</p>
                            <button class="btn {{ $business->is_verified ? 'btn-danger-outline' : 'btn-primary' }} w-full"><x-icon name="badge-check" class="size-4" />{{ $business->is_verified ? __('admin.businesses.unverify') : __('admin.businesses.verify') }}</button>
                        </form>
                    </x-dash.panel>
                @endcan

                @can('businesses.update')
                    <x-dash.panel :title="__('admin.businesses.owner')">
                        @if($business->owner)
                            <div class="mb-4 flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-gold-300">{{ $business->owner->initials() }}</span>
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-navy-900">{{ $business->owner->name }}</span><span class="block truncate text-xs text-muted-strong" dir="ltr">{{ $business->owner->email }}</span></span>
                                @can('users.view')<a href="{{ route('admin.users.show', $business->owner->id) }}" class="ms-auto btn btn-ghost btn-sm"><x-icon name="arrow-up-right" class="size-4" /></a>@endcan
                            </div>
                        @else
                            <p class="mb-3 text-sm text-muted-strong">{{ __('admin.businesses.no_owner_hint') }}</p>
                        @endif
                        <form method="POST" action="{{ route('admin.businesses.owner', $business->id) }}" class="space-y-3">
                            @csrf
                            <x-form.input name="owner_email" type="email" dir="ltr" :label="__('admin.businesses.owner_email')" :value="$business->owner?->email" />
                            <button class="btn btn-outline w-full">{{ __('admin.businesses.save_owner') }}</button>
                        </form>
                    </x-dash.panel>
                @endcan
            @endif

            @can('businesses.delete')
                <x-dash.panel :title="__('admin.businesses.danger_zone')">
                    @if($business->trashed())
                        <form method="POST" action="{{ route('admin.businesses.restore-deleted', $business->id) }}">@csrf
                            <button class="btn btn-primary w-full"><x-icon name="rotate-ccw" class="size-4" />{{ __('admin.businesses.restore_deleted') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.businesses.destroy', $business->id) }}" data-confirm="{{ __('admin.businesses.confirm_delete') }}" data-confirm-label="{{ __('common.delete') }}">
                            @csrf @method('DELETE')
                            <p class="mb-3 text-xs text-muted-strong">{{ __('admin.businesses.delete_hint') }}</p>
                            <button class="btn btn-danger-outline w-full"><x-icon name="trash-2" class="size-4" />{{ __('admin.businesses.delete') }}</button>
                        </form>
                    @endif
                </x-dash.panel>
            @endcan
        </aside>
    </div>
</x-layouts.dashboard>
