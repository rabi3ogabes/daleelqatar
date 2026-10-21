@php
    $sourceTone = ['google' => 'badge-info', 'website' => 'badge-success', 'email' => 'badge-success', 'ai' => 'badge-gold', 'inferred' => 'badge-muted'];
    $confTone = ['high' => 'badge-success', 'medium' => 'badge-warning', 'low' => 'badge-danger'];
    $runner = [
        'url' => route('admin.enrichment.bulk'),
        'counts' => ['missing_arabic' => $stats['missing_arabic'], 'missing_description' => $stats['missing_description'], 'missing_website' => $stats['missing_website'],
            'missing_location' => $stats['missing_location'], 'not_enriched' => $stats['not_enriched'], 'all' => $stats['total']],
        'ai' => $setup['ai'], 'google' => $setup['google'],
    ];
@endphp
<x-layouts.dashboard area="admin" :title="__('enrichment.title')" :subtitle="__('enrichment.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('enrichment.title')]]">
    <x-flash class="mb-5" />

    @if(! $setup['google'] || ! $setup['ai'])
        <section class="mb-6 overflow-hidden rounded-2xl border border-gold-200 bg-gradient-to-br from-gold-50 to-white p-5 shadow-soft">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="key-round" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-navy-900">{{ __('enrichment.setup.title') }}</h2>
                    <p class="mt-1 text-sm text-muted-strong">{{ __('enrichment.setup.text') }}</p>
                    <ul class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach(['google' => 'GOOGLE_MAPS_API_KEY', 'ai' => 'ANTHROPIC_API_KEY'] as $key => $env)
                            <li class="rounded-xl border border-navy-100 bg-white p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-navy-900">{{ __('enrichment.setup.'.$key) }}</span>
                                    <span class="badge {{ $setup[$key] ? 'badge-success' : 'badge-warning' }}"><x-icon :name="$setup[$key] ? 'check' : 'triangle-alert'" class="size-3" />{{ $setup[$key] ? __('enrichment.setup.ready') : __('enrichment.setup.missing') }}</span>
                                </div>
                                <p class="mt-1.5 text-xs leading-5 text-muted-strong">{{ __('enrichment.setup.'.$key.'_text') }}</p>
                                @unless($setup[$key])<code class="mt-2 inline-block rounded bg-navy-50 px-2 py-1 text-xs text-navy-800" dir="ltr">{{ $env }}</code>@endunless
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endif

    {{-- Data quality --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
        <x-dash.stat :label="__('enrichment.stats.pending')" :value="$stats['pending']" icon="inbox" :href="route('admin.enrichment.index', ['tab' => 'pending'])" :sublabel="__('enrichment.stats.pending_hint')" />
        <x-dash.stat :label="__('enrichment.stats.missing_arabic')" :value="$stats['missing_arabic']" icon="languages" :sublabel="__('enrichment.stats.of_total', ['total' => format_number($stats['total'])])" />
        <x-dash.stat :label="__('enrichment.stats.missing_description')" :value="$stats['missing_description']" icon="file-text" :sublabel="__('enrichment.stats.of_total', ['total' => format_number($stats['total'])])" />
        <x-dash.stat :label="__('enrichment.stats.no_website')" :value="$stats['no_website_found']" icon="globe" :href="route('admin.enrichment.index', ['tab' => 'no_website'])" :sublabel="__('enrichment.stats.missing_website', ['count' => format_number($stats['missing_website'])])" />
        <x-dash.stat :label="__('enrichment.stats.missing_location')" :value="$stats['missing_location']" icon="map-pin" :sublabel="__('enrichment.stats.on_google', ['count' => format_number($stats['on_google'])])" />
        <x-dash.stat :label="__('enrichment.stats.not_enriched')" :value="$stats['not_enriched']" icon="sparkles" :sublabel="__('enrichment.stats.of_total', ['total' => format_number($stats['total'])])" />
    </div>

    {{-- Bulk runner --}}
    <x-dash.panel class="mt-6" :title="__('enrichment.bulk.title')" :subtitle="__('enrichment.bulk.subtitle')">
        <div x-data="enrichmentRunner(@js($runner))" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="space-y-4">
                <fieldset>
                    <legend class="label">{{ __('enrichment.bulk.mode') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach(['full', 'names'] as $mode)
                            <label class="flex cursor-pointer gap-3 rounded-xl border p-3 transition" :class="mode === '{{ $mode }}' ? 'border-gold-400 bg-gold-50/60 ring-1 ring-gold-300' : 'border-navy-100 hover:border-gold-300'">
                                <input type="radio" value="{{ $mode }}" x-model="mode" class="mt-1 accent-navy-900" :disabled="busy">
                                <span><span class="block text-sm font-semibold text-navy-900">{{ __('enrichment.bulk.mode_'.$mode) }}</span><span class="mt-0.5 block text-xs leading-5 text-muted-strong">{{ __('enrichment.bulk.mode_'.$mode.'_hint') }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div x-show="mode === 'full'" class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="label">{{ __('enrichment.bulk.scope') }}</span>
                        <select x-model="scope" class="select" :disabled="busy">
                            @foreach(\App\Http\Controllers\Admin\EnrichmentController::SCOPES as $scope)
                                <option value="{{ $scope }}">{{ __('enrichment.scopes.'.$scope) }} ({{ format_number($runner['counts'][$scope]) }})</option>
                            @endforeach
                        </select>
                    </label>
                    <fieldset>
                        <legend class="label">{{ __('enrichment.bulk.sources') }}</legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-2 pt-1 text-sm text-navy-800">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="google" :disabled="busy || !cfg.google" class="accent-navy-900">{{ __('enrichment.sources.google') }}</label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="website" :disabled="busy" class="accent-navy-900">{{ __('enrichment.sources.website') }}</label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" x-model="ai" :disabled="busy || !cfg.ai" class="accent-navy-900">{{ __('enrichment.sources.ai') }}</label>
                        </div>
                    </fieldset>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-primary" x-show="!busy" @click="start()" :disabled="mode === 'names' && !cfg.ai"><x-icon name="play" class="size-4" />{{ __('enrichment.bulk.start') }}</button>
                    <button type="button" class="btn btn-outline" x-show="busy" x-cloak @click="stop()"><x-icon name="circle-x" class="size-4" />{{ __('enrichment.bulk.stop') }}</button>
                    <p class="text-xs text-muted-strong" x-show="!busy">{{ __('enrichment.bulk.keep_open') }}</p>
                </div>
            </div>
            <div class="rounded-2xl border border-navy-100 bg-navy-50/40 p-5" aria-live="polite">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-navy-900" x-text="busy ? @js(__('enrichment.bulk.running')) : (finished ? @js(__('enrichment.bulk.finished')) : @js(__('enrichment.bulk.idle')))"></p>
                    <p class="text-xs tabular-nums text-muted-strong"><span x-text="processed.toLocaleString()"></span> / <span x-text="(processed + remaining).toLocaleString()"></span></p>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white ring-1 ring-navy-100"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600 transition-all duration-500" :style="`width: ${percent}%`"></div></div>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-white p-3"><dt class="text-[0.7rem] text-muted-strong">{{ __('enrichment.bulk.processed') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-navy-900" x-text="processed.toLocaleString()">0</dd></div>
                    <div class="rounded-xl bg-white p-3"><dt class="text-[0.7rem] text-muted-strong">{{ __('enrichment.bulk.suggested') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-success" x-text="suggested.toLocaleString()">0</dd></div>
                    <div class="rounded-xl bg-white p-3"><dt class="text-[0.7rem] text-muted-strong">{{ __('enrichment.bulk.errors') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums" :class="errors ? 'text-danger' : 'text-navy-900'" x-text="errors.toLocaleString()">0</dd></div>
                </dl>
                <p x-show="error" x-cloak class="mt-3 rounded-lg bg-danger-soft px-3 py-2 text-xs text-danger" x-text="error"></p>
                <a x-show="finished && suggested > 0" x-cloak href="{{ route('admin.enrichment.index', ['tab' => 'pending']) }}" class="btn btn-gold btn-sm mt-4 w-full">{{ __('enrichment.bulk.review_now') }}</a>
            </div>
        </div>
    </x-dash.panel>

    {{-- Queue --}}
    <x-dash.panel class="mt-6" :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-navy-50 px-3 pt-3 scrollbar-none" aria-label="{{ __('enrichment.title') }}">
            @foreach(\App\Http\Controllers\Admin\EnrichmentController::TABS as $t)
                <a href="{{ route('admin.enrichment.index', ['tab' => $t]) }}" @class(['shrink-0 rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition', 'border-gold-500 text-navy-900' => $tab === $t, 'border-transparent text-muted-strong hover:text-navy-900' => $tab !== $t]) @if($tab === $t) aria-current="page" @endif>
                    {{ __('enrichment.tabs.'.$t) }}
                    @if($t === 'pending' && $stats['pending'])<span class="ms-1 rounded-full bg-gold-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-gold-800">{{ format_number($stats['pending']) }}</span>@endif
                    @if($t === 'no_website' && $stats['no_website_found'])<span class="ms-1 rounded-full bg-navy-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-navy-700">{{ format_number($stats['no_website_found']) }}</span>@endif
                </a>
            @endforeach
        </nav>

        @if($items->isEmpty())
            <div class="p-5"><x-empty-state :icon="$tab === 'no_website' ? 'globe' : 'sparkles'" :title="__('enrichment.empty.'.$tab)" :text="__('enrichment.empty.'.$tab.'_text')" /></div>
        @elseif($tab === 'no_website')
            <x-dash.table>
                <thead><tr>
                    <th class="text-start">{{ __('enrichment.table.business') }}</th>
                    <th class="text-start">{{ __('enrichment.table.email') }}</th>
                    <th class="text-start">{{ __('enrichment.table.checked') }}</th>
                    <th class="text-end">{{ __('common.actions') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($items as $b)
                        <tr>
                            <td><a href="{{ route('admin.businesses.show', $b->id) }}" class="font-medium text-navy-900 hover:text-gold-700">{{ $b->t('name') }}</a><span class="block text-xs text-muted">{{ $b->category?->t('name') }}</span></td>
                            <td class="text-xs text-muted-strong" dir="ltr">{{ $b->email ?: __('directory.na') }}</td>
                            <td class="text-xs text-muted-strong">{{ __('enrichment.no_website_reason') }}<span class="block">{{ $b->enriched_at?->diffForHumans() }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.enrichment.business', $b->id) }}" class="inline">@csrf<button class="btn btn-ghost btn-sm" data-busy><x-icon name="rotate-ccw" class="size-4" />{{ __('enrichment.recheck') }}</button></form>
                                <a href="{{ route('admin.businesses.edit', $b->id) }}" class="btn btn-outline btn-sm"><x-icon name="pencil" class="size-4" />{{ __('enrichment.add_website') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $items->links() }}</div>
        @else
            <x-dash.table>
                <thead><tr>
                    <th class="text-start">{{ __('enrichment.table.business') }}</th>
                    <th class="text-start">{{ __('enrichment.table.suggestions') }}</th>
                    <th class="text-start">{{ __('enrichment.table.match') }}</th>
                    <th class="text-start">{{ __('enrichment.table.when') }}</th>
                    <th class="text-end"></th>
                </tr></thead>
                <tbody>
                    @foreach($items as $s)
                        <tr>
                            <td class="max-w-[16rem]">
                                <a href="{{ route('admin.enrichment.show', $s) }}" class="font-medium text-navy-900 hover:text-gold-700">{{ $s->business?->name_en }}</a>
                                <span class="block truncate text-xs text-muted" dir="rtl">{{ \App\Support\Import\Normalizer::hasArabic((string) $s->business?->name_ar) ? $s->business->name_ar : ($s->fields['name_ar']['value'] ?? '') }}</span>
                            </td>
                            <td>
                                <div class="flex max-w-md flex-wrap gap-1">
                                    @forelse(array_keys((array) $s->fields) as $field)
                                        <span class="badge {{ $sourceTone[$s->fields[$field]['source'] ?? ''] ?? 'badge-muted' }}">{{ __('enrichment.fields.'.$field) }}</span>
                                    @empty
                                        <span class="text-xs text-muted">{{ __('enrichment.nothing_found') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if($s->match_confidence !== null)
                                    <span class="badge {{ $confTone[$s->confidenceLevel()] }}">{{ __('enrichment.confidence.'.$s->confidenceLevel()) }} · {{ $s->match_confidence }}%</span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-muted-strong">{{ $s->created_at?->diffForHumans() }}@if($s->reviewer)<span class="block">{{ $s->reviewer->name }}</span>@endif</td>
                            <td class="text-end"><a href="{{ route('admin.enrichment.show', $s) }}" class="btn btn-outline btn-sm">{{ $s->isPending() ? __('enrichment.review') : __('enrichment.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $items->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
