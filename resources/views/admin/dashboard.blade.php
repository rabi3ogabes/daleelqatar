<x-layouts.dashboard area="admin" :title="__('admin.dashboard.title')" :subtitle="__('admin.dashboard.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())">
    <x-slot:actions>
        @can('businesses.create')<a href="{{ route('admin.businesses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.businesses.create') }}</a>@endcan
    </x-slot:actions>

    {{-- Live visitors --}}
    @can('analytics.view')
        @php $live = app(\App\Support\Analytics\SiteAnalytics::class)->realtime(); @endphp
        <a href="{{ route('admin.analytics.index') }}" class="group relative mb-4 flex flex-wrap items-center gap-x-6 gap-y-3 overflow-hidden rounded-2xl bg-navy-900 px-5 py-4 text-white shadow-soft transition hover:shadow-lift">
            <div class="pattern-lattice absolute inset-0 opacity-30" aria-hidden="true"></div>
            <span class="relative flex items-center gap-3">
                <span class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-3 rounded-full bg-emerald-400"></span></span>
                <span class="text-sm font-semibold text-white/80">{{ __('analytics.live') }}</span>
            </span>
            <span class="relative text-3xl font-bold tabular-nums">{{ format_number($live['active']) }} <span class="text-sm font-normal text-white/60">{{ __('analytics.live_hint') }}</span></span>
            <span class="relative hidden min-w-0 flex-1 items-end gap-[2px] sm:flex" style="height: 2rem" dir="ltr" aria-hidden="true">
                @php $peak = max(1, max($live['minutes'])); @endphp
                @foreach($live['minutes'] as $v)<span class="flex-1 rounded-t-sm {{ $v ? 'bg-gold-300/80' : 'bg-white/10' }}" style="height: {{ max(8, round(100 * $v / $peak)) }}%"></span>@endforeach
            </span>
            <span class="relative ms-auto inline-flex items-center gap-1 text-sm font-semibold text-gold-300 group-hover:underline">{{ __('analytics.open_analytics') }}<x-icon name="chevron-right" class="size-4" /></span>
        </a>
    @endcan

    {{-- AdSense readiness --}}
    @can('settings.manage')
        @php $adsense = app(\App\Support\AdsenseReadiness::class)->report(); $open = $adsense['counts']['fail'] + $adsense['counts']['warn']; @endphp
        @if($open > 0)
            <a href="{{ route('admin.adsense.index') }}" class="card card-hover mb-4 flex flex-wrap items-center gap-4 px-5 py-4">
                <span @class(['flex size-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold tabular-nums', 'bg-danger-soft text-danger' => $adsense['counts']['fail'] > 0, 'bg-warning-soft text-warning' => $adsense['counts']['fail'] === 0])>{{ $adsense['score'] }}%</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-navy-900">{{ __('adsense.widget_title') }} · {{ __('adsense.verdict.'.($adsense['counts']['fail'] === 0 ? 'almost' : ($adsense['counts']['fail'] <= 2 ? 'close' : 'work')).'.title') }}</span>
                    <span class="block text-sm text-muted-strong">
                        @if($adsense['counts']['fail']){{ trans_choice('adsense.counts.fail', $adsense['counts']['fail'], ['count' => $adsense['counts']['fail']]) }}@endif
                        @if($adsense['counts']['fail'] && $adsense['counts']['warn']) · @endif
                        @if($adsense['counts']['warn']){{ trans_choice('adsense.counts.warn', $adsense['counts']['warn'], ['count' => $adsense['counts']['warn']]) }}@endif
                    </span>
                </span>
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-gold-700">{{ __('adsense.widget_open') }}<x-icon name="chevron-right" class="size-4" /></span>
            </a>
        @endif
    @endcan

    {{-- Key figures --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-dash.stat :label="__('admin.dashboard.total_businesses')" :value="$totals['businesses']" icon="store" :trend="$trends['businesses']" :sublabel="__('admin.dashboard.all_statuses')" :href="route('admin.businesses.index')" />
        <x-dash.stat :label="__('admin.dashboard.approved_businesses')" :value="$totals['approved']" icon="badge-check" :trend="$trends['approved']" :sublabel="__('admin.dashboard.published')" :href="route('admin.businesses.index', ['status' => 'approved'])" />
        <x-dash.stat :label="__('admin.dashboard.pending')" :value="$totals['pending']" icon="clock" :sublabel="__('admin.dashboard.awaiting_review')" :href="route('admin.businesses.index', ['status' => 'pending'])" />
        <x-dash.stat :label="__('admin.dashboard.users')" :value="$totals['users']" icon="users" :trend="$trends['users']" :sublabel="trans_choice('admin.dashboard.owners_count', $totals['owners'], ['count' => format_number($totals['owners'])])" />
    </div>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-5">
        @foreach([
            ['rejected_suspended', 'ban', route('admin.businesses.index', ['status' => 'suspended'])],
            ['owners', 'briefcase', route('admin.users.index', ['role' => 'business-owner'])],
            ['categories', 'folder-tree', route('admin.categories.index')],
            ['active_offers', 'tag', route('admin.offers.index')],
            ['pending_reviews', 'star', route('admin.reviews.index', ['status' => 'pending'])],
        ] as [$key, $icon, $href])
            <a href="{{ $href }}" class="card card-hover flex items-center gap-3 p-4">
                <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700"><x-icon :name="$icon" class="size-4.5" /></span>
                <span class="min-w-0">
                    <span class="block text-lg leading-tight font-bold text-navy-900 tabular-nums">{{ format_number($totals[$key]) }}</span>
                    <span class="block truncate text-xs text-muted-strong">{{ __('admin.dashboard.'.$key) }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- Listing quality: what is still missing and what is waiting for review --}}
    @if($quality)
        <x-dash.panel class="mt-6" :title="__('enrichment.quality.title')" :subtitle="__('enrichment.quality.subtitle')">
            <x-slot:actions>
                <a href="{{ route('admin.enrichment.index') }}" class="btn btn-primary btn-sm"><x-icon name="sparkles" class="size-4" />{{ __('enrichment.quality.cta') }}</a>
            </x-slot:actions>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['pending', 'inbox', route('admin.enrichment.index', ['tab' => 'pending'])],
                    ['missing_arabic', 'languages', route('admin.enrichment.index')],
                    ['no_website_found', 'globe', route('admin.enrichment.index', ['tab' => 'no_website'])],
                    ['missing_description', 'file-text', route('admin.enrichment.index')],
                    ['missing_location', 'map-pin', route('admin.enrichment.index')],
                ] as [$key, $icon, $href])
                    @php $pct = $quality['total'] > 0 && $key !== 'pending' ? round($quality[$key] / $quality['total'] * 100) : null; @endphp
                    <a href="{{ $href }}" class="group rounded-xl border border-navy-100 p-4 transition hover:border-gold-300 hover:bg-gold-50/40">
                        <span class="flex items-center justify-between gap-2">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 group-hover:bg-white"><x-icon :name="$icon" class="size-4.5" /></span>
                            @if($pct !== null)<span class="text-xs font-semibold tabular-nums text-muted-strong">{{ $pct }}%</span>@endif
                        </span>
                        <span class="mt-3 block text-xl font-bold tabular-nums text-navy-900">{{ format_number($quality[$key]) }}</span>
                        <span class="block text-xs text-muted-strong">{{ __('enrichment.quality.'.$key) }}</span>
                        @if($pct !== null)<span class="mt-2 block h-1 overflow-hidden rounded-full bg-navy-100"><span class="block h-full rounded-full bg-gold-500" style="width: {{ $pct }}%"></span></span>@endif
                    </a>
                @endforeach
            </div>
        </x-dash.panel>
    @endif

    {{-- Charts --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-dash.panel class="xl:col-span-2" :title="__('admin.dashboard.growth')" :subtitle="__('admin.dashboard.growth_hint')">
            @if(array_sum($growth) > 0)
                <x-chart.line :labels="$months" :values="$growth" :title="__('admin.dashboard.growth')" :value-label="__('admin.dashboard.listings')" />
            @else
                <x-empty-state icon="chart-line" :title="__('dashboard.no_data')" class="border-0 py-10" />
            @endif
        </x-dash.panel>
        <x-dash.panel :title="__('admin.dashboard.distribution')" :subtitle="__('admin.dashboard.distribution_hint')">
            @if($distribution)
                {{-- Narrow card: stack the donut above its legend so values never clip. --}}
                <div class="[&>figure]:flex-col">
                    <x-chart.donut :segments="$distribution" :title="__('admin.dashboard.distribution')" :center-label="__('admin.dashboard.published')" />
                </div>
            @else
                <x-empty-state icon="chart-pie" :title="__('dashboard.no_data')" class="border-0 py-10" />
            @endif
        </x-dash.panel>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-dash.panel :title="__('admin.dashboard.approvals')" :subtitle="__('dashboard.last_12_months')">
            <x-chart.bars :labels="$months" :series="$approvalSeries" :title="__('admin.dashboard.approvals')" />
        </x-dash.panel>
        <x-dash.panel :title="__('admin.dashboard.activity_chart')" :subtitle="__('dashboard.last_30_days')">
            <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted-strong">
                <span>{{ __('admin.dashboard.views_total') }}: <strong class="text-navy-900">{{ format_number(array_sum($daily['views'])) }}</strong> <span class="text-muted">({{ $trends['views'] !== null ? ($trends['views'] >= 0 ? '+' : '').$trends['views'].'%' : '—' }})</span></span>
                <span>{{ __('admin.dashboard.searches_total') }}: <strong class="text-navy-900">{{ format_number(array_sum($daily['searches'])) }}</strong></span>
            </div>
            <x-chart.line :labels="$daily['labels']" :values="$daily['views']" :title="__('admin.dashboard.views_total')" :value-label="__('admin.dashboard.views_unit')" color="#2E68B0" :height="190" />
        </x-dash.panel>
    </div>

    {{-- Lists --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-dash.panel class="xl:col-span-2" :title="__('admin.dashboard.recent_submissions')" :padding="false">
            <x-slot:actions><a href="{{ route('admin.businesses.index', ['status' => 'pending']) }}" class="link text-sm">{{ __('common.view_all') }}</a></x-slot:actions>
            <ul class="divide-y divide-navy-50">
                @forelse($recentSubmissions as $business)
                    <li>
                        <a href="{{ route('admin.businesses.show', $business->id) }}" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-gold-50/40">
                            <x-business-cover :business="$business" class="size-12 shrink-0 rounded-xl" sizes="48px" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">{{ $business->t('name') }}</span>
                                <span class="block truncate text-xs text-muted-strong">{{ $business->category?->t('name') }} · {{ $business->locationLabel() ?? '—' }} · {{ $business->owner?->name ?? __('admin.businesses.no_owner') }}</span>
                            </span>
                            <span class="hidden text-xs text-muted sm:block">{{ $business->submitted_at?->diffForHumans() }}</span>
                            <x-status-badge :status="$business->status" />
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-muted-strong">{{ __('admin.dashboard.no_submissions') }}</li>
                @endforelse
            </ul>
        </x-dash.panel>

        <x-dash.panel :title="__('admin.dashboard.recent_users')" :padding="false">
            <x-slot:actions>@can('users.view')<a href="{{ route('admin.users.index') }}" class="link text-sm">{{ __('common.view_all') }}</a>@endcan</x-slot:actions>
            <ul class="divide-y divide-navy-50">
                @forelse($recentUsers as $u)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-semibold text-gold-300">{{ $u->initials() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-navy-900">{{ $u->name }}</span>
                            <span class="block truncate text-xs text-muted-strong" dir="ltr">{{ $u->email }}</span>
                        </span>
                        <span class="text-xs text-muted">{{ $u->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                @endforelse
            </ul>
        </x-dash.panel>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-dash.panel :title="__('admin.dashboard.recent_activity')" :padding="false">
            <x-slot:actions>@can('audit.view')<a href="{{ route('admin.audit.index') }}" class="link text-sm">{{ __('common.view_all') }}</a>@endcan</x-slot:actions>
            <ul class="divide-y divide-navy-50">
                @forelse($activity as $log)
                    <li class="flex items-start gap-3 px-5 py-3">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="activity" class="size-4" /></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="font-medium text-navy-900">{{ $log->user?->name ?? __('admin.audit.system') }}</span>
                            <span class="text-muted-strong">— {{ \App\Support\AuditLabels::action($log->action) }}</span>
                            @if($log->auditable_type)<span class="block text-xs text-muted">{{ \App\Support\AuditLabels::subject($log->auditable_type) }} #{{ $log->auditable_id }}</span>@endif
                        </span>
                        <span class="shrink-0 text-xs text-muted">{{ $log->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                @endforelse
            </ul>
        </x-dash.panel>

        <x-dash.panel :title="__('admin.dashboard.top_searches')" :subtitle="__('dashboard.last_30_days')" :padding="false">
            <ol class="divide-y divide-navy-50">
                @forelse($topSearches as $i => $row)
                    <li class="flex items-center gap-3 px-5 py-3 text-sm">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-xs font-bold text-navy-700">{{ $i + 1 }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium text-navy-900">{{ $row->q }}</span>
                        <span class="text-xs text-muted-strong tabular-nums">{{ trans_choice('admin.dashboard.searches_count', $row->total, ['count' => format_number($row->total)]) }}</span>
                        <span @class(['badge', 'badge-danger' => (int) $row->avg_results === 0, 'badge-neutral' => (int) $row->avg_results > 0])>{{ trans_choice('admin.dashboard.avg_results', (int) $row->avg_results, ['count' => format_number($row->avg_results)]) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                @endforelse
            </ol>
        </x-dash.panel>
    </div>
</x-layouts.dashboard>
