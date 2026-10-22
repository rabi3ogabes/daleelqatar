<x-layouts.dashboard area="admin" :title="__('analytics.title')" :subtitle="__('admin.analytics.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('analytics.title')]]">
    <x-slot:actions>
        <div class="flex rounded-xl bg-white p-1 shadow-soft ring-1 ring-navy-100" role="group" aria-label="{{ __('admin.analytics.range') }}">
            @foreach(\App\Http\Controllers\Admin\AnalyticsController::RANGES as $d)
                <a href="{{ route('admin.analytics.index', ['tab' => 'directory', 'days' => $d]) }}" @class(['rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-navy-900 text-white' => $days === $d, 'text-navy-700 hover:bg-navy-50' => $days !== $d]) @if($days === $d) aria-current="true" @endif>{{ __('admin.analytics.days', ['count' => $d]) }}</a>
            @endforeach
        </div>
    </x-slot:actions>

    @include('admin.analytics.partials.tabs', ['tab' => 'directory'])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach(['registrations' => 'user-plus', 'submissions' => 'inbox', 'approvals' => 'badge-check', 'views' => 'eye', 'searches' => 'search', 'offer_views' => 'tag'] as $key => $icon)
            <x-dash.stat :label="__('admin.analytics.'.$key)" :value="$summary[$key]['value']" :icon="$icon" :trend="$summary[$key]['trend']" :sublabel="__('admin.analytics.days', ['count' => $days])" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-dash.panel :title="__('admin.analytics.views')">
            <x-chart.line :labels="$labels" :values="$viewsDaily" :title="__('admin.analytics.views')" color="#2E68B0" :height="200" />
        </x-dash.panel>
        <x-dash.panel :title="__('admin.analytics.searches')">
            <x-chart.line :labels="$labels" :values="$searchDaily" :title="__('admin.analytics.searches')" :height="200" />
        </x-dash.panel>
        <x-dash.panel :title="__('admin.analytics.registrations')">
            <x-chart.line :labels="$labels" :values="$registrationsDaily" :title="__('admin.analytics.registrations')" color="#16917F" :height="200" />
        </x-dash.panel>
        <x-dash.panel :title="__('admin.analytics.interactions')" :subtitle="__('admin.analytics.interactions_hint')">
            @php $segments = collect(\App\Enums\BusinessEventType::trackable())->map(fn ($t) => ['label' => $t->label(), 'value' => (int) ($interactions[$t->value] ?? 0)])->all(); @endphp
            @if(array_sum(array_column($segments, 'value')) > 0)
                <x-chart.donut :segments="$segments" :title="__('admin.analytics.interactions')" :center-label="__('admin.analytics.interactions')" />
            @else
                <x-empty-state icon="mouse-pointer-click" :title="__('dashboard.no_data')" class="border-0 py-8" />
            @endif
        </x-dash.panel>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        @foreach([['top_queries', $topQueries, true], ['zero_results', $zeroResults, false]] as [$key, $rows, $withAvg])
            <x-dash.panel :title="__('admin.analytics.'.$key)" :padding="false">
                @if($rows->isNotEmpty())
                    <x-dash.table>
                        <thead><tr><th>{{ __('admin.analytics.query') }}</th><th class="!text-end">{{ __('admin.analytics.searches') }}</th>@if($withAvg)<th class="!text-end">{{ __('admin.analytics.avg_results') }}</th>@endif</tr></thead>
                        <tbody>@foreach($rows as $r)<tr><td class="font-medium text-navy-900">{{ $r->q }}</td><td class="text-end tabular-nums">{{ format_number($r->total) }}</td>@if($withAvg)<td class="text-end tabular-nums">{{ format_number($r->avg_results) }}</td>@endif</tr>@endforeach</tbody>
                    </x-dash.table>
                @else
                    <p class="px-5 py-8 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-dash.panel>
        @endforeach
        <x-dash.panel :title="__('admin.analytics.top_businesses')" :padding="false">
            <ol class="divide-y divide-navy-50">
                @forelse($topBusinesses as $i => $b)
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-navy-50 text-xs font-bold text-navy-700">{{ $i + 1 }}</span>
                        <a href="{{ route('admin.businesses.show', $b->id) }}" class="min-w-0 flex-1 truncate font-medium text-navy-900 hover:text-gold-800">{{ app()->getLocale() === 'ar' ? $b->name_ar : $b->name_en }}</a>
                        <span class="tabular-nums text-muted-strong">{{ format_number($b->total) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</li>
                @endforelse
            </ol>
            @if($topOffers->isNotEmpty())
                <div class="border-t border-navy-50 px-5 py-3">
                    <p class="mb-2 text-xs font-semibold text-muted-strong">{{ __('admin.analytics.top_offers') }}</p>
                    @foreach($topOffers as $o)<p class="flex justify-between gap-3 text-sm"><span class="truncate text-navy-900">{{ app()->getLocale() === 'ar' ? $o->title_ar : $o->title_en }}</span><span class="tabular-nums text-muted-strong">{{ format_number($o->views_count) }}</span></p>@endforeach
                </div>
            @endif
        </x-dash.panel>
    </div>
</x-layouts.dashboard>
