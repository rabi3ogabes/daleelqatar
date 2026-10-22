@php
    use App\Support\Insights\BusinessInsights;
    $change = fn ($m) => BusinessInsights::change($current[$m], $previous[$m]);
    $headline = ['views', 'impressions', 'contacts', 'qr_scans'];
    $values = $current + ['contacts' => $contacts];
    $prevValues = $previous + ['contacts' => $previousContacts];
    $icons = collect(BusinessInsights::METRICS)->map(fn ($m) => $m[0])->all() + ['contacts' => 'mouse-pointer-click'];
@endphp
<x-layouts.account :title="__('insights.title')" :subtitle="$business->t('name')"
    :breadcrumbs="[['label' => __('account.businesses.title'), 'url' => route('account.businesses.index')], ['label' => $business->t('name'), 'url' => route('account.businesses.show', $business->uuid)], ['label' => __('insights.title')]]">
    <x-slot:actions>
        <a href="{{ route('account.businesses.poster', $business->uuid) }}" class="btn btn-outline"><x-icon name="qr-code" class="size-4" />{{ __('poster.open') }}</a>
    </x-slot:actions>

    {{-- Period --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-1 overflow-x-auto rounded-xl bg-navy-50/70 p-1" aria-label="{{ __('insights.period_label') }}">
            @foreach(BusinessInsights::PERIODS as $p)
                <a href="{{ route('account.businesses.insights', [$business->uuid, 'period' => $p]) }}" @class(['whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition', 'bg-white text-navy-900 shadow-sm' => $period === $p, 'text-muted-strong hover:text-navy-900' => $period !== $p]) @if($period === $p) aria-current="page" @endif>{{ __('insights.period.'.$p) }}</a>
            @endforeach
        </nav>
        <p class="text-xs text-muted-strong">{{ format_date($from) }} – {{ format_date($to) }} · {{ __('insights.compared') }}</p>
    </div>

    @unless($business->isPublished())
        <p class="mb-6 flex items-center gap-2 rounded-2xl bg-info-soft p-4 text-sm text-info"><x-icon name="info" class="size-4" />{{ __('account.businesses.stats_unpublished') }}</p>
    @endunless

    {{-- Headline KPIs --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($headline as $m)
            <x-dash.stat :label="__('insights.metric.'.$m)" :value="$values[$m]" :icon="$icons[$m]"
                :trend="BusinessInsights::change($values[$m], $prevValues[$m])" :sublabel="__('insights.hint.'.$m)" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-dash.panel :title="__('insights.trend_title')" :subtitle="__('insights.trend_hint')">
                @if(array_sum($daily['views']) + array_sum($daily['impressions']) > 0)
                    <x-chart.lines :labels="$daily['labels']" :title="__('insights.trend_title')" :series="[
                        ['label' => __('insights.metric.impressions'), 'values' => $daily['impressions'], 'color' => '#C9A24A', 'area' => true],
                        ['label' => __('insights.metric.views'), 'values' => $daily['views'], 'color' => '#101C32'],
                    ]" />
                @else
                    <p class="py-10 text-center text-sm text-muted-strong">{{ __('insights.no_data') }}</p>
                @endif
            </x-dash.panel>

            {{-- Funnel: seen → opened → contacted --}}
            <x-dash.panel :title="__('insights.funnel_title')" :subtitle="__('insights.funnel_hint')">
                @php $max = max(1, $current['impressions'], $current['views'], $contacts); @endphp
                <ol class="space-y-3">
                    @foreach([['impressions', $current['impressions']], ['views', $current['views']], ['contacts', $contacts]] as [$m, $v])
                        <li>
                            <div class="flex items-center justify-between text-sm"><span class="flex items-center gap-2 text-navy-900"><x-icon :name="$icons[$m]" class="size-4 text-gold-600" />{{ __('insights.metric.'.$m) }}</span><span class="font-semibold tabular-nums text-navy-900">{{ format_number($v) }}</span></div>
                            <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full bg-gradient-to-r from-navy-800 to-navy-600 rtl:bg-gradient-to-l" style="width: {{ max(2, round($v / $max * 100)) }}%"></div></div>
                        </li>
                    @endforeach
                </ol>
                @if($conversion !== null)
                    <p class="mt-4 rounded-xl bg-gold-50 px-4 py-3 text-sm text-navy-900">{!! __('insights.conversion', ['pct' => '<strong>'.format_number($conversion, 1).'%</strong>']) !!}</p>
                @endif
            </x-dash.panel>

            <x-dash.panel :title="__('insights.actions_title')">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach(['calls', 'whatsapp', 'directions', 'website', 'shares', 'favorites', 'quotes', 'reviews'] as $m)
                        @php $c = $change($m); @endphp
                        <div class="rounded-xl border border-navy-100 p-3">
                            <p class="flex items-center gap-1.5 text-xs text-muted-strong"><x-icon :name="$icons[$m]" class="size-3.5 text-gold-600" />{{ __('insights.metric.'.$m) }}</p>
                            <p class="mt-1.5 flex items-baseline gap-2">
                                <span class="text-xl font-bold tabular-nums text-navy-900">{{ format_number($current[$m]) }}</span>
                                @if($c !== null)<span @class(['text-xs font-semibold', 'text-success' => $c >= 0, 'text-danger' => $c < 0])>{{ $c >= 0 ? '+' : '' }}{{ $c }}%</span>@endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-dash.panel>
        </div>

        <aside class="space-y-6">
            {{-- Category benchmark --}}
            <section class="relative overflow-hidden rounded-2xl bg-navy-900 p-5 text-white">
                <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-xs font-medium uppercase tracking-wide text-gold-300">{{ __('insights.benchmark_title') }}</p>
                    @if($average && $average['views'] > 0)
                        @php $ratio = $current['views'] / $average['views']; @endphp
                        <p class="mt-3 text-3xl font-bold tabular-nums">{{ format_number($ratio, 1) }}×</p>
                        <p class="mt-1 text-sm text-white/75">{{ __('insights.benchmark_views', ['category' => $business->category?->t('name'), 'avg' => format_number($average['views'], 1)]) }}</p>
                        @if($average['contacts'] > 0)
                            <p class="mt-3 border-t border-white/10 pt-3 text-sm text-white/75">{{ __('insights.benchmark_contacts', ['mine' => format_number($contacts), 'avg' => format_number($average['contacts'], 1)]) }}</p>
                        @endif
                        <p class="mt-3 text-xs text-white/50">{{ __('insights.benchmark_note', ['count' => format_number($average['listings'])]) }}</p>
                    @else
                        <p class="mt-3 text-sm text-white/75">{{ __('insights.benchmark_none') }}</p>
                    @endif
                </div>
            </section>

            @if($tips)
                <x-dash.panel :title="__('insights.tips_title')" :subtitle="__('insights.tips_hint')">
                    <ul class="space-y-2">
                        @foreach($tips as $tip)
                            <li>
                                <a href="{{ route($tip['route'], $tip['params']) }}" class="group flex items-start gap-3 rounded-xl p-2.5 transition hover:bg-navy-50">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gold-50 text-gold-700 ring-1 ring-gold-200"><x-icon :name="$tip['icon']" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-navy-900">{{ trans_choice('insights.tip.'.$tip['key'].'_title', $tip['count'] ?? 1, ['count' => $tip['count'] ?? 0, 'season' => $tip['season'] ?? '']) }}</span>
                                        <span class="block text-xs text-muted-strong">{{ __('insights.tip.'.$tip['key'].'_text', ['season' => $tip['season'] ?? '']) }}</span>
                                    </span>
                                    <x-icon name="chevron-right" class="mt-2 size-4 text-muted transition group-hover:text-gold-700 rtl:rotate-180" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-dash.panel>
            @endif

            <a href="{{ route('account.businesses.poster', $business->uuid) }}" class="card card-hover flex items-start gap-3 p-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="qr-code" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-navy-900">{{ __('poster.card_title') }}</span>
                    <span class="mt-0.5 block text-xs text-muted-strong">{{ __('poster.card_text') }}</span>
                </span>
            </a>
            <p class="px-1 text-xs leading-relaxed text-muted">{{ __('insights.privacy') }}</p>
        </aside>
    </div>
</x-layouts.account>
