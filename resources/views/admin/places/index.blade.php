@php
    $categoryNames = [];
    $categoryOptions = [];
    foreach ($categories as $root) {
        $categoryNames[$root->id] = $root->t('name');
        $group = [$root->id => $root->t('name')];
        foreach ($root->children as $child) {
            $categoryNames[$child->id] = $child->t('name');
            $group[$child->id] = '— '.$child->t('name');
        }
        $categoryOptions[$root->t('name')] = $group;
    }
    $areas = $municipalities->mapWithKeys(fn ($m) => [$m->id => $m->areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->t('name')])->values()])->all();
    $states = collect(['new', 'likely', 'possible', 'linked', 'closed', 'added'])->mapWithKeys(fn ($s) => [$s => __('places.state.'.$s)])->all();
    $cfg = [
        'urls' => [
            'search' => route('admin.places.search'),
            'add' => route('admin.places.add'),
            'addMany' => route('admin.places.add-many'),
            'review' => route('admin.places.review'),
            'enrich' => route('admin.enrichment.business', ['business' => '__ID__']),
        ],
        'radius' => 2,
        'areas' => $areas,
        'categoryNames' => $categoryNames,
        'fallbackCategory' => $fallbackCategory,
        'i18n' => [
            'states' => $states,
            'reasons' => trans('enrichment.reasons'),
            'keywordRequired' => __('places.search.keyword_required'),
            'pinRequired' => __('places.search.pin_required'),
            'locationFailed' => __('places.search.location_failed'),
            'network' => __('places.errors.network'),
            // Plural forms as Laravel picks them (Arabic has its own for 2 and for 3–10).
            'reviews' => collect(['one' => 1, 'two' => 2, 'few' => 5, 'many' => 20])->map(fn ($n) => trans_choice('places.results.reviews', $n, ['count' => ':count'])),
            'updates' => collect(['one' => 1, 'two' => 2, 'few' => 5, 'many' => 20])->map(fn ($n) => trans_choice('places.actions.open_review', $n, ['count' => ':count'])),
            'confirmTitle' => __('places.bulk.confirm_title'),
            'confirmText' => __('places.bulk.confirm_text'),
            'confirmPublish' => __('places.bulk.confirm_publish'),
            'confirmDraft' => __('places.bulk.confirm_draft'),
            'add' => __('places.bulk.add'),
            'done' => __('places.bulk.done'),
            'noProgress' => __('places.bulk.no_progress'),
        ],
    ];
    $stateTone = ['new' => 'badge-gold', 'likely' => 'badge-info', 'possible' => 'badge-warning', 'linked' => 'badge-success', 'closed' => 'badge-muted', 'added' => 'badge-success'];
    $quick = $categories->take(14);
@endphp
<x-layouts.dashboard area="admin" :title="__('places.title')" :subtitle="__('places.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('places.title')]]">
    <x-slot:actions>
        <span class="hidden items-center gap-1.5 text-xs text-muted-strong md:inline-flex"><x-icon name="map-pinned" class="size-4 text-gold-600" />{{ trans_choice('places.linked_count', $linked, ['count' => number_format($linked)]) }}</span>
        <a href="{{ route('admin.enrichment.index') }}" class="btn btn-outline"><x-icon name="sparkles" class="size-4" />{{ __('places.open_enrichment') }}</a>
    </x-slot:actions>

    @unless($enabled)
        <section class="mb-6 flex items-start gap-4 rounded-2xl border border-gold-200 bg-gradient-to-br from-gold-50 to-white p-5 shadow-soft">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="key-round" class="size-5" /></span>
            <div class="min-w-0">
                <h2 class="font-semibold text-navy-900">{{ __('places.not_configured_title') }}</h2>
                <p class="mt-1 text-sm text-muted-strong">{{ __('places.not_configured_text') }}</p>
            </div>
        </section>
    @endunless

    <div x-data="placeFinder(@js($cfg))" @keydown.window.escape="modal.open ? closeModal() : (busy || clearSelection())">
        {{-- Search --}}
        <section class="card overflow-hidden">
            <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <form class="space-y-5 p-5 sm:p-6" @submit.prevent="search()" novalidate>
                    <div>
                        <label for="finder-keyword" class="label">{{ __('places.search.what') }}</label>
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-5 -translate-y-1/2 text-muted" />
                            <input id="finder-keyword" x-ref="keyword" type="text" x-model="keyword" maxlength="120" autocomplete="off" class="input ps-11 text-base" placeholder="{{ __('places.search.placeholder') }}" @disabled(! $enabled)>
                        </div>
                        <div class="mt-3">
                            <p class="text-[0.7rem] font-semibold tracking-wide text-muted-strong">{{ __('places.search.quick') }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($quick as $category)
                                    <button type="button" class="rounded-full border px-3 py-1 text-xs font-medium transition"
                                        :class="keyword === @js($category->name_en) ? 'border-navy-900 bg-navy-900 text-gold-200' : 'border-navy-100 bg-white text-navy-800 hover:border-gold-300 hover:bg-gold-50'"
                                        @click="quick(@js($category->name_en))">{{ $category->t('name') }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="label">{{ __('places.search.where') }}</p>
                        <div class="inline-flex rounded-xl bg-navy-50 p-1" role="radiogroup">
                            @foreach(['area' => ['layers', 'places.search.mode_area'], 'point' => ['locate', 'places.search.mode_point']] as $value => [$icon, $label])
                                <button type="button" role="radio" :aria-checked="(mode === '{{ $value }}').toString()" @click="mode = '{{ $value }}'"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold transition"
                                    :class="mode === '{{ $value }}' ? 'bg-white text-navy-900 shadow-soft' : 'text-muted-strong hover:text-navy-900'">
                                    <x-icon :name="$icon" class="size-4" />{{ __($label) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2" x-show="mode === 'area'">
                            <div>
                                <label for="finder-municipality" class="label">{{ __('places.search.municipality') }}</label>
                                <select id="finder-municipality" class="select" x-model="municipalityId">
                                    <option value="">{{ __('places.search.all_qatar') }}</option>
                                    @foreach($municipalities as $m)<option value="{{ $m->id }}">{{ $m->t('name') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label for="finder-area" class="label">{{ __('places.search.area') }}</label>
                                <select id="finder-area" class="select" x-model="areaId" :disabled="!municipalityId || !areas.length">
                                    <option value="">{{ __('places.search.any_area') }}</option>
                                    <template x-for="a in areas" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3" x-show="mode === 'point'" x-cloak>
                            <p class="flex items-center gap-2 text-sm" :class="lat === null ? 'text-muted-strong' : 'font-medium text-navy-900'">
                                <x-icon name="map-pin" class="size-4 text-gold-600" />
                                <span x-text="lat === null ? @js(__('places.search.pick_hint')) : @js(__('places.search.pin_set')).replace(':lat', lat).replace(':lng', lng)" dir="auto"></span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-muted-strong">{{ __('places.search.radius') }}</span>
                                @foreach($radii as $r)
                                    <button type="button" class="rounded-full border px-3 py-1 text-xs font-semibold tabular-nums transition" @click="radius = {{ $r }}"
                                        :class="Number(radius) === {{ $r }} ? 'border-navy-900 bg-navy-900 text-gold-200' : 'border-navy-100 text-navy-800 hover:border-gold-300'">{{ __('places.search.km', ['count' => $r]) }}</button>
                                @endforeach
                                <button type="button" class="btn btn-ghost btn-sm ms-auto" @click="locate()"><x-icon name="navigation" class="size-4" />{{ __('places.search.my_location') }}</button>
                            </div>
                        </div>
                    </div>

                    <p class="flex items-start gap-2 rounded-xl bg-danger-soft px-3 py-2.5 text-sm text-danger" x-show="error" x-cloak role="alert"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span x-text="error"></span></p>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn btn-gold" :disabled="searching || busy" @disabled(! $enabled)>
                            <x-icon name="search" class="size-4" x-show="!searching" /><x-icon name="loader-circle" class="size-4 animate-spin" x-show="searching" x-cloak />
                            <span x-text="searching ? @js(__('places.search.searching')) : @js(__('places.search.go'))">{{ __('places.search.go') }}</span>
                        </button>
                        <p class="text-xs text-muted">{{ __('places.search.cost_hint') }}</p>
                    </div>
                </form>

                <div class="relative min-h-[20rem] border-t border-navy-50 bg-navy-50 lg:border-s lg:border-t-0">
                    <div x-ref="map" class="absolute inset-0 z-0" role="application" aria-label="{{ __('places.search.mode_point') }}"></div>
                    <p class="pointer-events-none absolute inset-x-3 bottom-3 z-[500] rounded-xl bg-navy-900/85 px-3 py-2 text-center text-xs text-white backdrop-blur" x-show="mode === 'point' && lat === null" x-cloak>{{ __('places.search.pick_hint') }}</p>
                </div>
            </div>
        </section>

        {{-- Results --}}
        <section class="card mt-6 overflow-hidden scroll-mt-24" x-ref="results">
            <template x-if="!searched">
                <div class="p-5"><x-empty-state icon="map-pinned" :title="__('places.results.empty_title')" :text="__('places.results.empty_text')" /></div>
            </template>

            <template x-if="searched">
                <div>
                    <header class="flex flex-wrap items-center gap-3 border-b border-navy-50 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">{{ __('places.results.title') }}</p>
                            <h2 class="mt-0.5 truncate font-semibold text-navy-900" dir="auto" x-text="@js(__('places.results.for')).replace(':query', query)"></h2>
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-muted-strong">
                            <input type="checkbox" class="checkbox" x-model="hideClosed">{{ __('places.results.hide_closed') }}
                        </label>
                    </header>

                    <div class="flex flex-wrap items-center gap-2 border-b border-navy-50 px-5 py-3">
                        <div class="flex flex-wrap gap-1" role="tablist">
                            @foreach(['all' => 'filter_all', 'new' => 'filter_new', 'matched' => 'filter_matched', 'listed' => 'filter_listed'] as $key => $label)
                                <button type="button" role="tab" :aria-selected="(filter === '{{ $key }}').toString()" @click="filter = '{{ $key }}'"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                                    :class="filter === '{{ $key }}' ? 'bg-navy-900 text-white' : 'text-muted-strong hover:bg-navy-50 hover:text-navy-900'">
                                    {{ __('places.results.'.$label) }}
                                    <span class="rounded-full px-1.5 text-[0.7rem] tabular-nums" :class="filter === '{{ $key }}' ? 'bg-gold-400 text-navy-950' : 'bg-navy-100 text-navy-800'" x-text="count('{{ $key }}')"></span>
                                </button>
                            @endforeach
                        </div>
                        <label class="ms-auto inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-navy-900" x-show="selectableVisible.length">
                            <input type="checkbox" class="checkbox" :checked="allNewSelected" @change="toggleAllNew($event.target.checked)" :disabled="busy">{{ __('places.results.select_new') }}
                        </label>
                    </div>

                    <template x-if="!visible.length">
                        <div class="p-5"><x-empty-state icon="search" :title="__('places.results.none_title')" :text="__('places.results.none_text')" /></div>
                    </template>

                    <ul class="divide-y divide-navy-50">
                        <template x-for="row in visible" :key="row.place_id">
                            <li :id="`place-${row.place_id}`" class="relative px-5 py-4 transition duration-500"
                                :class="{ 'bg-gold-50/80': highlight === row.place_id || selected[row.place_id], 'opacity-60': isClosed(row) }">
                                <div class="flex flex-wrap gap-x-4 gap-y-3 sm:flex-nowrap">
                                    <div class="pt-1">
                                        <input type="checkbox" class="checkbox" x-show="selectable(row)" x-model="selected[row.place_id]" :disabled="busy" :aria-label="row.name">
                                        <span x-show="!selectable(row)" class="block size-4"></span>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-navy-900" dir="auto" x-text="row.name"></h3>
                                            <span class="badge" :class="@js($stateTone)[stateOf(row)]" x-text="cfg.i18n.states[stateOf(row)]"></span>
                                            <span class="badge badge-danger" x-show="isClosed(row)">{{ __('places.state.closed') }}</span>
                                            <span class="badge badge-warning" x-show="row.business_status === 'CLOSED_TEMPORARILY'">{{ __('places.state.temporarily_closed') }}</span>
                                            <span class="text-xs text-muted" x-show="row.type" x-text="row.type"></span>
                                        </div>
                                        <p class="mt-1 text-sm text-muted-strong" dir="auto" x-text="row.address"></p>
                                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-strong">
                                            <span x-show="row.rating" class="inline-flex items-center gap-1" title="{{ __('places.results.google_rating') }}">
                                                <x-icon name="star" class="size-3.5 fill-gold-400 text-gold-500" /><span class="font-semibold text-navy-900" x-text="Number(row.rating).toFixed(1)"></span>
                                                <span x-text="'(' + reviewsLabel(row.rating_count) + ')'"></span>
                                            </span>
                                            <span x-show="row.phone" class="inline-flex items-center gap-1" dir="ltr"><x-icon name="phone" class="size-3.5" /><span x-text="row.phone"></span></span>
                                            <a x-show="row.website" :href="row.website" target="_blank" rel="noopener noreferrer nofollow" class="inline-flex items-center gap-1 hover:text-gold-700" dir="ltr"><x-icon name="globe" class="size-3.5" /><span x-text="domain(row.website)"></span></a>
                                            <a :href="row.maps_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-gold-700 hover:underline"><x-icon name="external-link" class="size-3.5" />{{ __('places.results.open_maps') }}</a>
                                            <button type="button" x-show="row.lat !== null" class="inline-flex items-center gap-1 hover:text-navy-900" @click="showOnMap(row)"><x-icon name="map-pin" class="size-3.5" />{{ __('places.results.show_on_map') }}</button>
                                        </div>

                                        {{-- New place: proposed category --}}
                                        <template x-if="row.state === 'new' && !rowState[row.place_id]">
                                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                                <span class="font-semibold text-muted-strong">{{ __('places.results.suggested_category') }}</span>
                                                <template x-if="editingCategory !== row.place_id">
                                                    <span class="inline-flex items-center gap-2">
                                                        <span class="badge" :class="isFallback(row) ? 'badge-warning' : 'badge-neutral'" x-text="isFallback(row) ? @js(__('places.results.choose_category')) : categoryName(categoryFor[row.place_id])"></span>
                                                        <button type="button" class="font-semibold text-gold-700 hover:underline" @click="editingCategory = row.place_id" :disabled="busy">{{ __('places.results.change') }}</button>
                                                    </span>
                                                </template>
                                                <template x-if="editingCategory === row.place_id">
                                                    <select class="select h-9 max-w-xs py-1 text-sm" x-model.number="categoryFor[row.place_id]" @change="editingCategory = null" @blur="editingCategory = null" x-init="$nextTick(() => $el.focus())">
                                                        @foreach($categoryOptions as $group => $options)
                                                            <optgroup label="{{ $group }}">
                                                                @foreach($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                                            </optgroup>
                                                        @endforeach
                                                    </select>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Matched listing --}}
                                        <template x-if="row.business && rowState[row.place_id]?.status !== 'added'">
                                            <div class="mt-3 rounded-xl border border-navy-100 bg-navy-50/50 px-3 py-2.5">
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                                    <span class="text-xs font-semibold text-muted-strong">{{ __('places.results.matches') }}</span>
                                                    <a :href="row.business.edit_url" class="font-semibold text-navy-900 hover:text-gold-700" dir="auto" x-text="row.business.name"></a>
                                                    <span class="text-xs text-muted" x-text="[row.business.category, row.business.location].filter(Boolean).join(' · ')"></span>
                                                    <span class="text-xs font-semibold text-navy-800" x-show="row.score && row.state !== 'linked'" x-text="@js(__('places.results.score')).replace(':score', row.score)"></span>
                                                </div>
                                                <div class="mt-1.5 flex flex-wrap gap-1" x-show="reasonLabels(row).length">
                                                    <template x-for="r in reasonLabels(row)" :key="r.key">
                                                        <span class="rounded-full px-2 py-0.5 text-[0.7rem] font-medium" :class="r.good ? 'bg-success-soft text-success' : 'bg-white text-muted-strong ring-1 ring-navy-100'" x-text="r.label"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Outcome of an action --}}
                                        <template x-if="rowState[row.place_id]?.status === 'added'">
                                            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-success-soft px-3 py-2 text-sm text-success">
                                                <x-icon name="circle-check" class="size-4" />
                                                <span class="font-semibold" dir="auto" x-text="rowState[row.place_id].business.name"></span>
                                                <span class="text-xs" x-text="rowState[row.place_id].business.status_label"></span>
                                            </div>
                                        </template>
                                        <template x-if="['same', 'error'].includes(rowState[row.place_id]?.status)">
                                            <p class="mt-3 flex items-center gap-2 text-sm" :class="rowState[row.place_id].status === 'error' ? 'text-danger' : 'text-success'">
                                                <x-icon name="info" class="size-4" /><span x-text="rowState[row.place_id].message"></span>
                                            </p>
                                        </template>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex w-full shrink-0 flex-col items-start gap-2 ps-8 sm:w-auto sm:items-end sm:ps-0">
                                        <template x-if="rowState[row.place_id]?.status === 'added'">
                                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                                <form method="POST" :action="cfg.urls.enrich.replace('__ID__', rowState[row.place_id].business.id)" target="_blank">
                                                    @csrf
                                                    <button class="btn btn-outline btn-sm" data-no-busy><x-icon name="sparkles" class="size-4" />{{ __('places.actions.enrich') }}</button>
                                                </form>
                                                <a :href="rowState[row.place_id].business.edit_url" target="_blank" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" />{{ __('places.actions.open_listing') }}</a>
                                            </div>
                                        </template>
                                        <template x-if="rowState[row.place_id]?.status === 'comparing'">
                                            <span class="btn btn-outline btn-sm pointer-events-none"><x-icon name="loader-circle" class="size-4 animate-spin" />{{ __('places.actions.comparing') }}</span>
                                        </template>
                                        <template x-if="rowState[row.place_id]?.status === 'review'">
                                            <a :href="rowState[row.place_id].url" target="_blank" class="btn btn-gold btn-sm"><x-icon name="external-link" class="size-4" /><span x-text="reviewLabel(rowState[row.place_id].count)"></span></a>
                                        </template>
                                        <template x-if="!['added', 'comparing', 'review'].includes(rowState[row.place_id]?.status)">
                                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                                <template x-if="row.business">
                                                    <button type="button" class="btn btn-sm" :class="row.state === 'linked' ? 'btn-outline' : 'btn-primary'" @click="compare(row)" :disabled="busy"><x-icon name="refresh-cw" class="size-4" />{{ __('places.actions.compare') }}</button>
                                                </template>
                                                <template x-if="row.business && row.state !== 'linked'">
                                                    <button type="button" class="btn btn-ghost btn-sm" @click="openAdd(row, true)" :disabled="busy">{{ __('places.actions.add_new') }}</button>
                                                </template>
                                                <template x-if="!row.business && ['new', 'closed'].includes(row.state)">
                                                    <button type="button" class="btn btn-sm" :class="isClosed(row) ? 'btn-outline' : 'btn-gold'" @click="openAdd(row)" :disabled="busy"><x-icon name="plus" class="size-4" />{{ __('places.actions.add') }}</button>
                                                </template>
                                                <template x-if="row.business">
                                                    <a :href="row.business.view_url || row.business.edit_url" target="_blank" class="btn-icon size-9 text-muted hover:text-navy-900" :title="row.business.view_url ? @js(__('places.actions.view')) : @js(__('places.actions.open_listing'))"><x-icon name="eye" class="size-4" /></a>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <div class="border-t border-navy-50 px-5 py-4 text-center" :class="selectedIds.length || busy || summary ? 'pb-28' : ''">
                        <button type="button" class="btn btn-outline" x-show="next" @click="more()" :disabled="loadingMore">
                            <x-icon name="loader-circle" class="size-4 animate-spin" x-show="loadingMore" x-cloak /><x-icon name="chevron-down" class="size-4" x-show="!loadingMore" />
                            <span x-text="loadingMore ? @js(__('places.results.loading_more')) : @js(__('places.results.more'))"></span>
                        </button>
                        <p class="text-xs text-muted" x-show="!next && rows.length">{{ __('places.results.end') }}</p>
                    </div>
                </div>
            </template>
        </section>

        {{-- Floating bulk bar --}}
        <div class="fixed inset-x-0 bottom-4 z-40 flex justify-center px-4" x-show="selectedIds.length > 0 || busy || summary" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
            <div class="w-full max-w-3xl rounded-2xl bg-navy-900 p-3 text-white shadow-2xl ring-1 ring-gold-400/30 sm:p-4" role="region" aria-label="{{ __('places.bulk.bar_label') }}" aria-live="polite">
                <template x-if="busy">
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2"><x-icon name="loader-circle" class="size-4 animate-spin text-gold-300" />{{ __('places.bulk.adding') }}</span>
                            <span class="tabular-nums text-white/70"><span x-text="progress.done"></span> / <span x-text="progress.total"></span></span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600 transition-all duration-300" :style="`width: ${percent}%`"></div></div>
                        <div class="mt-2 flex justify-end"><button type="button" class="text-xs font-semibold text-white/70 hover:text-white" @click="stopping = true" x-show="!stopping">{{ __('places.bulk.stop') }}</button></div>
                    </div>
                </template>
                <template x-if="!busy && summary">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2"><x-icon name="circle-check" class="size-5 text-gold-300" /><span x-text="summary"></span></span>
                        <button type="button" class="btn btn-ghost btn-sm !text-white/80" @click="summary = ''">{{ __('common.close') }}</button>
                    </div>
                </template>
                <template x-if="!busy && !summary">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="flex size-8 items-center justify-center rounded-full bg-gold-400 font-bold tabular-nums text-navy-950" x-text="selectedIds.length"></span>
                            <span><span class="font-semibold" x-text="@js(__('places.bulk.selected')).replace(':count', selectedIds.length)"></span>
                                <button type="button" class="ms-2 text-xs text-white/60 hover:text-white" @click="clearSelection()">{{ __('places.bulk.clear') }}</button></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-white/80">
                                <input type="checkbox" class="checkbox" x-model="publish">{{ __('places.bulk.publish') }}
                            </label>
                            <button type="button" class="btn btn-gold btn-sm" @click="bulkAdd()"><x-icon name="plus" class="size-4" />{{ __('places.bulk.add') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Review & add --}}
        <template x-teleport="body">
            <div x-show="modal.open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="finder-add-title">
                <div x-show="modal.open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="closeModal()"></div>
                <div x-show="modal.open" x-transition x-trap.noscroll="modal.open" class="relative flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-lift sm:rounded-3xl">
                    <header class="flex items-start gap-4 border-b border-navy-50 px-6 pt-5 pb-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-gold-300"><x-icon name="map-pinned" class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">{{ __('places.modal.eyebrow') }}</p>
                            <h2 id="finder-add-title" class="mt-0.5 truncate text-lg font-semibold text-navy-900" dir="auto" x-text="modal.row?.name"></h2>
                        </div>
                        <button type="button" class="btn-icon text-muted-strong hover:bg-navy-50" @click="closeModal()" aria-label="{{ __('places.modal.cancel') }}"><x-icon name="x" class="size-5" /></button>
                    </header>

                    <form class="min-h-0 flex-1 overflow-y-auto px-6 py-5" id="finder-add-form" @submit.prevent="saveAdd()">
                        <p class="text-sm text-muted-strong">{{ __('places.modal.intro') }}</p>

                        <template x-if="modal.duplicate">
                            <div class="mt-4 rounded-2xl border border-warning/40 bg-warning-soft p-4">
                                <p class="flex items-center gap-2 font-semibold text-navy-900"><x-icon name="triangle-alert" class="size-5 text-warning" />{{ __('places.modal.duplicate_title') }}</p>
                                <p class="mt-1 text-sm text-navy-800">
                                    <a :href="modal.duplicate.edit_url" target="_blank" class="font-semibold underline-offset-2 hover:underline" dir="auto" x-text="modal.duplicate.name"></a>
                                    <span class="text-xs text-muted-strong" x-text="' · ' + [modal.duplicate.category, modal.duplicate.location].filter(Boolean).join(' · ')"></span>
                                </p>
                                <p class="mt-2 text-xs leading-5 text-muted-strong">{{ __('places.modal.duplicate_text') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" @click="compare(modal.row, modal.duplicate.id)"><x-icon name="refresh-cw" class="size-4" />{{ __('places.modal.compare_instead') }}</button>
                                    <button type="button" class="btn btn-outline btn-sm" @click="saveAdd(true)" :disabled="modal.saving">{{ __('places.modal.add_anyway') }}</button>
                                </div>
                            </div>
                        </template>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="fa-name-en">{{ __('places.modal.name_en') }} <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="fa-name-en" type="text" class="input" x-model="modal.form.name_en" maxlength="160" required dir="ltr">
                            </div>
                            <div>
                                <label class="label" for="fa-name-ar">{{ __('places.modal.name_ar') }}</label>
                                <input id="fa-name-ar" type="text" class="input" x-model="modal.form.name_ar" maxlength="160" dir="rtl" lang="ar">
                                <p class="mt-1 text-xs text-muted">{{ __('places.modal.name_ar_hint') }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label" for="fa-category">{{ __('places.modal.category') }}</label>
                                <select id="fa-category" class="select" x-model.number="modal.form.category_id">
                                    @foreach($categoryOptions as $group => $options)
                                        <optgroup label="{{ $group }}">
                                            @foreach($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="fa-municipality">{{ __('places.modal.municipality') }}</label>
                                <select id="fa-municipality" class="select" x-model="modal.form.municipality_id" @change="modal.form.area_id = ''">
                                    <option value="">—</option>
                                    @foreach($municipalities as $m)<option value="{{ $m->id }}">{{ $m->t('name') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="fa-area">{{ __('places.modal.area') }}</label>
                                <select id="fa-area" class="select" x-model="modal.form.area_id" :disabled="!modalAreas.length">
                                    <option value="">{{ __('places.modal.no_area') }}</option>
                                    <template x-for="a in modalAreas" :key="a.id"><option :value="String(a.id)" x-text="a.name" :selected="String(a.id) === String(modal.form.area_id)"></option></template>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="fa-phone">{{ __('places.modal.phone') }}</label>
                                <input id="fa-phone" type="tel" class="input" x-model="modal.form.phone" maxlength="40" dir="ltr">
                            </div>
                            <div>
                                <label class="label" for="fa-website">{{ __('places.modal.website') }}</label>
                                <input id="fa-website" type="url" class="input" x-model="modal.form.website" maxlength="300" dir="ltr">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label" for="fa-address">{{ __('places.modal.address') }}</label>
                                <input id="fa-address" type="text" class="input" x-model="modal.form.address_en" maxlength="300" dir="ltr">
                            </div>
                        </div>

                        <div class="mt-4 flex items-start gap-3 rounded-xl bg-navy-50/70 px-3 py-2.5 text-xs text-muted-strong" x-show="modal.row?.lat !== null">
                            <x-icon name="map-pin" class="mt-0.5 size-4 text-gold-600" />
                            <span><span class="font-semibold text-navy-900">{{ __('places.modal.location') }}</span> · <span dir="ltr" x-text="modal.row ? `${modal.row.lat?.toFixed(5)}, ${modal.row.lng?.toFixed(5)}` : ''"></span><br>{{ __('places.modal.location_hint') }}</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <label class="flex cursor-pointer items-center gap-3 text-sm text-navy-900"><input type="checkbox" class="checkbox" x-model="modal.form.hours">{{ __('places.modal.hours') }}</label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm text-navy-900"><input type="checkbox" class="checkbox mt-0.5" x-model="modal.form.publish">
                                <span>{{ __('places.modal.publish') }}<span class="block text-xs text-muted-strong">{{ __('places.modal.publish_hint') }}</span></span></label>
                        </div>
                        <p class="mt-4 flex items-start gap-2 text-xs leading-5 text-muted-strong"><x-icon name="sparkles" class="mt-0.5 size-4 text-gold-600" />{{ __('places.modal.after') }}</p>

                        <p class="mt-4 flex items-start gap-2 rounded-xl bg-danger-soft px-3 py-2.5 text-sm text-danger" x-show="modal.error" x-cloak role="alert"><x-icon name="circle-alert" class="mt-0.5 size-4" /><span x-text="modal.error"></span></p>
                    </form>

                    <footer class="flex items-center justify-end gap-2 border-t border-navy-50 px-6 py-4">
                        <button type="button" class="btn btn-ghost" @click="closeModal()" :disabled="modal.saving">{{ __('places.modal.cancel') }}</button>
                        <button type="submit" form="finder-add-form" class="btn btn-gold" :disabled="modal.saving || !modal.form.name_en?.trim()" data-no-busy>
                            <x-icon name="loader-circle" class="size-4 animate-spin" x-show="modal.saving" x-cloak /><x-icon name="plus" class="size-4" x-show="!modal.saving" />
                            <span x-text="modal.saving ? @js(__('places.modal.saving')) : @js(__('places.modal.save'))"></span>
                        </button>
                    </footer>
                </div>
            </div>
        </template>
    </div>
</x-layouts.dashboard>
