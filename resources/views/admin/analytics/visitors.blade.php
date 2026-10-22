@php
    use App\Http\Controllers\Admin\AnalyticsController;
    use App\Support\Analytics\Visit;
    use App\Support\ChartPalette;
    $t = $report['totals'];
    $duration = fn (int $s) => $s >= 60 ? __('analytics.duration_m', ['m' => intdiv($s, 60), 's' => $s % 60]) : __('analytics.duration_s', ['s' => $s]);
    $hasData = $t['views'] > 0;
    $deviceIcons = ['mobile' => 'smartphone', 'desktop' => 'monitor', 'tablet' => 'tablet'];
@endphp
<x-layouts.dashboard area="admin" :title="__('analytics.title')" :subtitle="__('analytics.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('analytics.title')]]">
    <x-slot:actions>
        <div class="flex rounded-xl bg-white p-1 shadow-soft ring-1 ring-navy-100" role="group" aria-label="{{ __('analytics.range') }}">
            @foreach(\App\Support\Analytics\SiteAnalytics::RANGES as $r)
                <a href="{{ route('admin.analytics.index', ['range' => $r]) }}" @class(['rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap', 'bg-navy-900 text-white' => $range === $r, 'text-navy-700 hover:bg-navy-50' => $range !== $r]) @if($range === $r) aria-current="true" @endif>{{ __('analytics.ranges.'.$r) }}</a>
            @endforeach
        </div>
    </x-slot:actions>

    @include('admin.analytics.partials.tabs', ['tab' => 'visitors'])

    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        {{-- Real time --}}
        <section x-data="realtimeAnalytics(@js(['url' => route('admin.analytics.realtime'), 'initial' => $realtime]))" class="relative overflow-hidden rounded-3xl bg-navy-900 p-5 text-white shadow-lift" aria-labelledby="rt-title">
            <div class="pattern-lattice absolute inset-0 opacity-40" aria-hidden="true"></div>
            <div class="absolute -top-20 -end-16 size-56 rounded-full bg-gold-400/15 blur-3xl" aria-hidden="true"></div>
            <div class="relative">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="rt-title" class="flex items-center gap-2 text-sm font-semibold text-white/80">
                        <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2.5 rounded-full bg-emerald-400"></span></span>
                        {{ __('analytics.realtime.title') }}
                    </h2>
                    <span class="text-[0.7rem] tabular-nums text-white/40" x-text="data.at"></span>
                </div>
                <p class="mt-4 text-6xl font-bold tabular-nums tracking-tight" x-text="data.active.toLocaleString()">{{ $realtime['active'] }}</p>
                <p class="mt-1 text-sm text-white/60">{{ __('analytics.realtime.active', ['minutes' => \App\Support\Analytics\SiteAnalytics::ACTIVE_MINUTES]) }}</p>

                {{-- Page views per minute, last 30 minutes --}}
                <div class="mt-5">
                    <div class="flex h-14 items-end gap-[2px]" dir="ltr" role="img" :aria-label="@js(__('analytics.realtime.per_minute')) + ': ' + data.views">
                        <template x-for="(v, i) in data.minutes" :key="i">
                            <span class="flex-1 rounded-t-[3px] bg-gold-300/80 transition-all duration-500" :style="`height:${Math.max(4, v / Math.max(1, ...data.minutes) * 100)}%`" :class="v === 0 ? '!bg-white/10' : ''" :title="v"></span>
                        </template>
                    </div>
                    <p class="mt-2 flex justify-between text-[0.7rem] text-white/45"><span>{{ __('analytics.realtime.ago_30') }}</span><span><span class="font-semibold text-white/80 tabular-nums" x-text="data.views.toLocaleString()"></span> {{ __('analytics.realtime.views_30') }}</span><span>{{ __('analytics.realtime.now') }}</span></p>
                </div>

                {{-- Devices now --}}
                <div class="mt-5 grid grid-cols-3 gap-2">
                    <template x-for="d in data.devices" :key="d.key">
                        <div class="rounded-xl bg-white/5 px-2 py-2.5 text-center ring-1 ring-white/10">
                            <p class="text-lg font-semibold tabular-nums" x-text="d.pct + '%'"></p>
                            <p class="text-[0.7rem] text-white/55" x-text="d.label"></p>
                        </div>
                    </template>
                </div>

                <div class="mt-5 space-y-4 border-t border-white/10 pt-4">
                    <div>
                        <p class="mb-2 text-xs font-semibold text-white/50">{{ __('analytics.realtime.pages') }}</p>
                        <ul class="space-y-1.5 text-sm">
                            <template x-for="p in data.pages.slice(0, 5)" :key="p.path">
                                <li class="flex items-center gap-2"><span class="min-w-0 flex-1 truncate text-white/85" dir="auto" x-text="p.title" :title="p.path"></span><span class="tabular-nums font-semibold" x-text="p.visitors"></span></li>
                            </template>
                            <li x-show="!data.pages.length" class="text-white/45">{{ __('analytics.realtime.nobody') }}</li>
                        </ul>
                    </div>
                    <div x-show="data.countries.length">
                        <p class="mb-2 text-xs font-semibold text-white/50">{{ __('analytics.realtime.countries') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="c in data.countries" :key="c.name">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs"><span x-text="c.flag"></span><span x-text="c.name"></span><span class="font-semibold tabular-nums" x-text="c.visitors"></span></span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="min-w-0 space-y-6">
            {{-- Key numbers --}}
            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                <x-dash.stat :label="__('analytics.kpi.visitors')" :value="$t['visitors']" icon="users" :trend="$report['trends']['visitors']" :sublabel="__('analytics.kpi.visitors_hint')" />
                <x-dash.stat :label="__('analytics.kpi.new_visitors')" :value="$t['new_visitors']" icon="user-plus" :trend="$report['trends']['new_visitors']" :sublabel="$t['visitors'] ? __('analytics.kpi.share', ['pct' => round(100 * $t['new_visitors'] / max(1, $t['visitors']))]) : null" />
                <x-dash.stat :label="__('analytics.kpi.views')" :value="$t['views']" icon="eye" :trend="$report['trends']['views']" :sublabel="__('analytics.kpi.per_visitor', ['n' => $t['per_visitor']])" />
                <x-dash.stat :label="__('analytics.kpi.sessions')" :value="$t['sessions']" icon="activity" />
                <x-dash.stat :label="__('analytics.kpi.engagement')" :value="$duration($t['engagement'])" icon="clock" :trend="$report['trends']['engagement']" :sublabel="__('analytics.kpi.engagement_hint')" />
                <x-dash.stat :label="__('analytics.kpi.bounce')" :value="$t['bounce'].'%'" icon="mouse-pointer-click" :sublabel="__('analytics.kpi.bounce_hint')" />
            </div>

            <x-dash.panel :title="__('analytics.chart_title')" :subtitle="$report['hourly'] ? __('analytics.chart_hourly') : __('analytics.chart_daily')">
                @if($hasData)
                    <x-chart.lines :labels="$report['series']['labels']" :title="__('analytics.chart_title')" :height="240" :series="[
                        ['label' => __('analytics.kpi.visitors'), 'values' => $report['series']['visitors'], 'color' => ChartPalette::CATEGORICAL[0], 'area' => true],
                        ['label' => __('analytics.kpi.views'), 'values' => $report['series']['views'], 'color' => ChartPalette::CATEGORICAL[1]],
                    ]" />
                @else
                    <x-empty-state icon="chart-column" :title="__('analytics.empty_title')" :text="__('analytics.empty_text')" class="border-0 py-10" />
                @endif
            </x-dash.panel>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-dash.panel :title="__('analytics.pages.title')" :subtitle="__('analytics.pages.subtitle')" :padding="false">
            @if($report['pages']->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-muted-strong">{{ __('analytics.no_data') }}</p>
            @else
                @php $topViews = max(1, $report['pages']->max('views')); @endphp
                <x-dash.table>
                    <thead><tr>
                        <th>{{ __('analytics.pages.page') }}</th>
                        <th class="!text-end">{{ __('analytics.kpi.views') }}</th>
                        <th class="!text-end">{{ __('analytics.kpi.visitors') }}</th>
                        <th class="!text-end">{{ __('analytics.pages.time') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach($report['pages'] as $p)
                            <tr>
                                <td class="max-w-0 w-full">
                                    <a href="{{ url($p->path) }}" target="_blank" rel="noopener" class="block truncate font-medium text-navy-900 hover:text-gold-800" dir="auto">{{ AnalyticsController::pageTitle($p->title, $p->path) }}</a>
                                    <span class="mt-1 block h-1 rounded-full bg-gold-300/80" style="width: {{ max(2, round(100 * $p->views / $topViews)) }}%" aria-hidden="true"></span>
                                    <span class="mt-0.5 block truncate text-xs text-muted" dir="ltr">{{ $p->path }}</span>
                                </td>
                                <td class="text-end tabular-nums font-semibold text-navy-900">{{ format_number($p->views) }}</td>
                                <td class="text-end tabular-nums">{{ format_number($p->visitors) }}</td>
                                <td class="whitespace-nowrap text-end tabular-nums text-muted-strong">{{ $duration((int) $p->engaged) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-dash.table>
            @endif
        </x-dash.panel>

        <x-dash.panel :title="__('analytics.sources.title')" :subtitle="__('analytics.sources.subtitle')">
            @php $channelSegments = $report['channels']->map(fn ($c) => ['label' => __('analytics.channels.'.$c->name), 'value' => (int) $c->visitors])->all(); @endphp
            @if(array_sum(array_column($channelSegments, 'value')) > 0)
                <x-chart.donut :segments="$channelSegments" :title="__('analytics.sources.title')" :center-label="__('analytics.kpi.visitors')" :max="6" stacked />
                @if($report['referrers']->isNotEmpty())
                    <div class="mt-5 border-t border-navy-50 pt-2">
                        <p class="px-3 pt-2 text-xs font-semibold text-muted-strong">{{ __('analytics.sources.referrers') }}</p>
                        @include('admin.analytics.partials.bars', ['rows' => $report['referrers']->map(fn ($r) => ['label' => $r->name, 'value' => (int) $r->visitors, 'sub' => __('analytics.channels.'.$r->channel), 'ltr' => true])->all()])
                    </div>
                @endif
            @else
                <p class="py-8 text-center text-sm text-muted-strong">{{ __('analytics.no_data') }}</p>
            @endif
        </x-dash.panel>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
        <x-dash.panel :title="__('analytics.countries.title')" :padding="false">
            @include('admin.analytics.partials.bars', ['rows' => $report['countries']->map(fn ($c) => ['lead' => Visit::flag($c->name), 'label' => Visit::countryName($c->name), 'value' => (int) $c->visitors])->all()])
            @if($report['cities']->isNotEmpty())
                <div class="border-t border-navy-50">
                    <p class="px-6 pt-4 text-xs font-semibold text-muted-strong">{{ __('analytics.countries.cities') }}</p>
                    @include('admin.analytics.partials.bars', ['rows' => $report['cities']->map(fn ($c) => ['lead' => Visit::flag($c->country), 'label' => $c->name, 'value' => (int) $c->visitors])->all()])
                </div>
            @endif
        </x-dash.panel>

        <x-dash.panel :title="__('analytics.devices.title')">
            @php $deviceSegments = $report['devices']->map(fn ($d) => ['label' => __('analytics.devices.'.$d->name), 'value' => (int) $d->visitors])->all(); @endphp
            @if(array_sum(array_column($deviceSegments, 'value')) > 0)
                <x-chart.donut :segments="$deviceSegments" :title="__('analytics.devices.title')" :center-label="__('analytics.kpi.visitors')" />
                <div class="mt-5 grid grid-cols-3 gap-2 border-t border-navy-50 pt-4 text-center">
                    @foreach(['mobile', 'desktop', 'tablet'] as $d)
                        @php $row = $report['devices']->firstWhere('name', $d); @endphp
                        <div class="rounded-xl bg-navy-50/60 px-2 py-3">
                            <x-icon :name="$deviceIcons[$d]" class="mx-auto size-5 text-gold-600" />
                            <p class="mt-1 text-sm font-semibold tabular-nums text-navy-900">{{ format_number((int) ($row->visitors ?? 0)) }}</p>
                            <p class="text-[0.7rem] text-muted-strong">{{ __('analytics.devices.'.$d) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="py-8 text-center text-sm text-muted-strong">{{ __('analytics.no_data') }}</p>
            @endif
        </x-dash.panel>

        <x-dash.panel :title="__('analytics.tech.title')" :padding="false">
            <p class="px-6 pt-4 text-xs font-semibold text-muted-strong">{{ __('analytics.tech.browsers') }}</p>
            @include('admin.analytics.partials.bars', ['rows' => $report['browsers']->map(fn ($b) => ['label' => $b->name ?? __('analytics.unknown'), 'value' => (int) $b->visitors])->all()])
            <div class="border-t border-navy-50">
                <p class="px-6 pt-4 text-xs font-semibold text-muted-strong">{{ __('analytics.tech.systems') }}</p>
                @include('admin.analytics.partials.bars', ['rows' => $report['systems']->map(fn ($o) => ['label' => $o->name ?? __('analytics.unknown'), 'value' => (int) $o->visitors])->all()])
            </div>
            <div class="border-t border-navy-50">
                <p class="px-6 pt-4 text-xs font-semibold text-muted-strong">{{ __('analytics.tech.languages') }}</p>
                @include('admin.analytics.partials.bars', ['rows' => $report['languages']->map(fn ($l) => ['label' => __('analytics.languages.'.$l->name), 'value' => (int) $l->visitors])->all()])
            </div>
        </x-dash.panel>
    </div>

    <p class="mt-6 flex items-start gap-2 px-1 text-xs leading-5 text-muted">
        <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-gold-600" />{{ __('analytics.privacy_note') }}
    </p>
</x-layouts.dashboard>
