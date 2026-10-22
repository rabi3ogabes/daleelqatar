@php
    use App\Models\CategoryReview;
    use App\Support\Categories\CategoryReviewer;
    $isAr = app()->getLocale() === 'ar';
    $levelTone = ['high' => 'bg-success', 'medium' => 'bg-warning', 'low' => 'bg-danger'];
    $levelBadge = ['high' => 'badge-success', 'medium' => 'badge-warning', 'low' => 'badge-danger'];
    $fieldIcon = ['label' => 'tag', 'name' => 'building-2', 'name_ar' => 'languages', 'tagline' => 'sparkles', 'services' => 'list', 'description' => 'file-text', 'ai' => 'sparkles', 'admin' => 'user'];
    $scanner = [
        'url' => route('admin.category-review.scan'),
        'ai' => $aiReady,
        'counts' => ['unchecked' => $stats['unchecked'], 'other' => $stats['in_other'], 'unsure' => null, 'all' => $stats['total']],
        'scope' => $stats['unchecked'] > 0 ? 'unchecked' : ($stats['in_other'] > 0 ? 'other' : 'all'),
    ];
    // One reason per kind of evidence (two for source labels), strongest first.
    $reasonsFor = function (CategoryReview $review): array {
        $seen = [];

        return array_values(array_filter((array) $review->reasons, function ($r) use (&$seen) {
            $field = $r['field'] ?? '';
            $seen[$field] = ($seen[$field] ?? 0) + 1;

            return $seen[$field] <= ($field === 'label' ? 2 : 1);
        }));
    };
    $highlight = function (string $text, ?string $keyword): string {
        $safe = e(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
        if (! $keyword) {
            return $safe;
        }
        $pattern = '/('.preg_quote(e($keyword), '/').'(?:es|s)?)/iu';

        return preg_replace($pattern, '<mark class="rounded bg-gold-100 px-0.5 text-navy-900">$1</mark>', $safe, 1) ?? $safe;
    };
    $path = fn ($category) => $category ? ($category->parent_id ? optional($category->parent)->t('name').' › ' : '').$category->t('name') : null;
    $filtered = array_filter($filters);
@endphp
<x-layouts.dashboard area="admin" :title="__('category_review.title')" :subtitle="__('category_review.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('category_review.title')]]">
    <x-flash class="mb-5" />

    {{-- Overview --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-6">
        <x-dash.stat :label="__('category_review.stats.checked')" :value="$stats['checked']" icon="scan-search" :sublabel="__('category_review.stats.checked_hint', ['unchecked' => format_number($stats['unchecked'])])" />
        <x-dash.stat :label="__('category_review.stats.proposed')" :value="$stats['proposed']" icon="layers" :href="route('admin.category-review.index')" :sublabel="__('category_review.stats.proposed_hint')" />
        <x-dash.stat :label="__('category_review.stats.high')" :value="$stats['high']" icon="badge-check" :href="route('admin.category-review.index', ['level' => 'high'])" :sublabel="__('category_review.stats.high_hint', ['percent' => CategoryReview::HIGH])" />
        <x-dash.stat :label="__('category_review.stats.correct')" :value="$stats['correct']" icon="circle-check" :sublabel="__('category_review.stats.correct_hint')" />
        <x-dash.stat :label="__('category_review.stats.unclear')" :value="$stats['unclear']" icon="circle-help" :href="route('admin.category-review.index', ['tab' => 'unclear'])" :sublabel="__('category_review.stats.unclear_hint')" />
        <x-dash.stat :label="__('category_review.stats.applied')" :value="$stats['applied']" icon="history" :href="route('admin.category-review.index', ['tab' => 'applied'])" :sublabel="__('category_review.stats.applied_hint', ['kept' => format_number($stats['kept'])])" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
        {{-- Scanner --}}
        <x-dash.panel :title="__('category_review.scan.title')" :subtitle="__('category_review.scan.subtitle')">
            <div x-data="categoryScanner(@js($scanner))" class="grid gap-5 lg:grid-cols-2">
                <div class="space-y-4">
                    <label class="block">
                        <span class="label">{{ __('category_review.scan.scope') }}</span>
                        <select x-model="scope" class="select" :disabled="busy">
                            @foreach(CategoryReviewer::SCOPES as $scope)
                                @continue($scope === 'unsure' && ! $aiReady)
                                <option value="{{ $scope }}">{{ __('category_review.scan.scopes.'.$scope) }}@if($scanner['counts'][$scope] !== null) ({{ format_number($scanner['counts'][$scope]) }})@endif</option>
                            @endforeach
                        </select>
                    </label>
                    @if($aiReady)
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-navy-100 p-3 transition hover:border-gold-300">
                            <input type="checkbox" x-model="ai" class="checkbox mt-0.5" :disabled="busy">
                            <span><span class="block text-sm font-semibold text-navy-900">{{ __('category_review.scan.ai') }}</span><span class="mt-0.5 block text-xs leading-5 text-muted-strong">{{ __('category_review.scan.ai_hint') }}</span></span>
                        </label>
                    @else
                        <p class="flex gap-2 rounded-xl bg-navy-50/70 px-3 py-2.5 text-xs leading-5 text-muted-strong"><x-icon name="key-round" class="mt-0.5 size-4 shrink-0 text-gold-700" />{{ __('category_review.scan.ai_missing') }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-primary" x-show="!busy" @click="start()"><x-icon name="play" class="size-4" />{{ __('category_review.scan.start') }}</button>
                        <button type="button" class="btn btn-outline" x-show="busy" x-cloak @click="stop()"><x-icon name="circle-x" class="size-4" />{{ __('category_review.scan.stop') }}</button>
                        <p class="text-xs text-muted-strong" x-show="busy" x-cloak>{{ __('category_review.scan.keep_open') }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-navy-100 bg-navy-50/40 p-4" aria-live="polite">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-sm font-semibold text-navy-900" x-text="busy ? @js(__('category_review.scan.running')) : (finished ? @js(__('category_review.scan.finished')) : @js(__('category_review.scan.idle')))"></p>
                        <p class="text-xs tabular-nums text-muted-strong" x-show="processed + remaining > 0"><span x-text="processed.toLocaleString()"></span> / <span x-text="(processed + remaining).toLocaleString()"></span></p>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-white ring-1 ring-navy-100"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600 transition-all duration-500" :style="`width: ${percent}%`"></div></div>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-white p-2.5"><dt class="text-[0.7rem] text-muted-strong">{{ __('category_review.scan.processed') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-navy-900" x-text="processed.toLocaleString()">0</dd></div>
                        <div class="rounded-xl bg-white p-2.5"><dt class="text-[0.7rem] text-muted-strong">{{ __('category_review.scan.suggested') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-gold-700" x-text="suggested.toLocaleString()">0</dd></div>
                        <div class="rounded-xl bg-white p-2.5"><dt class="text-[0.7rem] text-muted-strong">{{ __('category_review.scan.by_ai') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-navy-900" x-text="aiCount.toLocaleString()">0</dd></div>
                    </dl>
                    <p x-show="error" x-cloak class="mt-3 rounded-lg bg-danger-soft px-3 py-2 text-xs text-danger" x-text="error"></p>
                    <a x-show="finished" x-cloak href="{{ route('admin.category-review.index') }}" class="btn btn-gold btn-sm mt-3 w-full">{{ __('category_review.scan.reload') }}</a>
                </div>
            </div>
        </x-dash.panel>

        {{-- Apply the confident ones --}}
        <section class="relative overflow-hidden rounded-(--radius-card) bg-navy-900 p-6 text-white shadow-lift"
            x-data="confidentApplier(@js(['url' => route('admin.category-review.apply-confident'), 'total' => $stats['high'], 'confirm' => __('category_review.confident.confirm', ['count' => format_number($stats['high'])])]))">
            <div class="pattern-lattice pointer-events-none absolute inset-0 opacity-40" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -end-16 -top-16 size-56 rounded-full bg-gold-400/20 blur-3xl" aria-hidden="true"></div>
            <div class="relative">
                <span class="flex size-11 items-center justify-center rounded-xl bg-gold-400/15 text-gold-300 ring-1 ring-gold-400/30"><x-icon name="badge-check" class="size-5" /></span>
                <h2 class="mt-4 text-lg font-semibold text-white">{{ __('category_review.confident.title') }}</h2>
                @if($stats['high'] > 0)
                    <p class="mt-2 text-sm leading-6 text-white/70">{{ __('category_review.confident.text', ['count' => format_number($stats['high']), 'percent' => CategoryReview::HIGH]) }}</p>
                    <button type="button" class="btn btn-gold mt-5 w-full sm:w-auto" @click="run()" :disabled="busy">
                        <x-icon name="loader-circle" class="size-4 animate-spin" x-show="busy" x-cloak />
                        <x-icon name="check" class="size-4" x-show="!busy" />
                        <span x-show="!busy">{{ __('category_review.confident.button', ['count' => format_number($stats['high'])]) }}</span>
                        <span x-show="busy" x-cloak x-text="@js(__('category_review.confident.running', ['count' => '__N__'])).replace('__N__', applied.toLocaleString())"></span>
                    </button>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10" x-show="busy || applied" x-cloak><div class="h-full rounded-full bg-gold-400 transition-all duration-500" :style="`width: ${percent}%`"></div></div>
                    <p x-show="error" x-cloak class="mt-3 rounded-lg bg-danger/20 px-3 py-2 text-xs text-white" x-text="error"></p>
                @else
                    <p class="mt-2 text-sm leading-6 text-white/70">{{ __('category_review.confident.none') }}</p>
                @endif
            </div>
        </section>
    </div>

    {{-- Biggest moves --}}
    @if($moves !== [])
        <x-dash.panel class="mt-6" :title="__('category_review.moves.title')" :subtitle="__('category_review.moves.subtitle')">
            <ul class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach($moves as $move)
                    <li class="flex flex-col rounded-2xl border border-navy-100 bg-white p-4 transition hover:border-gold-300 hover:shadow-soft"
                        x-data="confidentApplier(@js(['url' => route('admin.category-review.apply-confident'), 'from' => $move['from']?->id, 'to' => $move['to']->id, 'total' => $move['high'], 'confirm' => __('category_review.moves.confirm', ['count' => format_number($move['high']), 'from' => $move['from']?->t('name') ?? __('category_review.moves.nowhere'), 'to' => $move['to']->t('name')])]))">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="min-w-0 truncate text-muted-strong line-through decoration-navy-200">{{ $move['from']?->t('name') ?? __('category_review.moves.nowhere') }}</span>
                            <x-icon name="arrow-right" class="size-4 shrink-0 text-gold-600 rtl:-scale-x-100" />
                            <span class="min-w-0 truncate font-semibold text-navy-900">{{ $move['to']->t('name') }}</span>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ $move['confidence'] >= CategoryReview::HIGH ? 'bg-success' : ($move['confidence'] >= CategoryReview::MEDIUM ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $move['confidence'] }}%"></div></div>
                            <span class="text-xs tabular-nums text-muted-strong">{{ $move['confidence'] }}%</span>
                        </div>
                        <p class="mt-2 text-xs text-muted-strong">{{ trans_choice('category_review.moves.listings', $move['total'], ['count' => format_number($move['total'])]) }}@if($move['high']) · <span class="font-medium text-success">{{ __('category_review.moves.confident', ['count' => format_number($move['high'])]) }}</span>@endif</p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-3">
                            <a href="{{ route('admin.category-review.index', array_filter(['from' => $move['from']?->id, 'to' => $move['to']->id])) }}" class="btn btn-outline btn-sm">{{ __('category_review.moves.review') }}</a>
                            @if($move['high'] > 0)
                                <button type="button" class="btn btn-primary btn-sm" @click="run()" :disabled="busy">
                                    <x-icon name="loader-circle" class="size-3.5 animate-spin" x-show="busy" x-cloak />{{ __('category_review.moves.apply_confident', ['count' => format_number($move['high'])]) }}
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-dash.panel>
    @endif

    {{-- Review queue --}}
    <x-dash.panel class="mt-6" :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-navy-50 px-3 pt-3 scrollbar-none" aria-label="{{ __('category_review.title') }}">
            @foreach(\App\Http\Controllers\Admin\CategoryReviewController::TABS as $t)
                @php $count = ['proposed' => $stats['proposed'], 'unclear' => $stats['unclear'], 'applied' => $stats['applied'], 'kept' => $stats['kept']][$t]; @endphp
                <a href="{{ route('admin.category-review.index', ['tab' => $t]) }}" @class(['shrink-0 rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition', 'border-gold-500 text-navy-900' => $tab === $t, 'border-transparent text-muted-strong hover:text-navy-900' => $tab !== $t]) @if($tab === $t) aria-current="page" @endif>
                    {{ __('category_review.tabs.'.$t) }}
                    @if($count)<span @class(['ms-1 rounded-full px-1.5 py-0.5 text-[0.65rem] font-bold', 'bg-gold-100 text-gold-800' => in_array($t, ['proposed', 'unclear'], true), 'bg-navy-100 text-navy-700' => ! in_array($t, ['proposed', 'unclear'], true)])>{{ format_number($count) }}</span>@endif
                </a>
            @endforeach
        </nav>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.category-review.index') }}" class="grid gap-3 border-b border-navy-50 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(0,1fr))_auto]">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="relative block">
                <span class="sr-only">{{ __('category_review.filters.search') }}</span>
                <x-icon name="search" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('category_review.filters.search') }}" class="input ps-9">
            </label>
            @if($tab === 'proposed')
                <label class="block"><span class="sr-only">{{ __('category_review.filters.level') }}</span>
                    <select name="level" class="select">
                        <option value="">{{ __('category_review.filters.any_level') }}</option>
                        @foreach(['high', 'medium', 'low'] as $level)<option value="{{ $level }}" @selected($filters['level'] === $level)>{{ __('category_review.levels.'.$level) }}</option>@endforeach
                    </select>
                </label>
            @endif
            @foreach(['from', 'to'] as $key)
                @continue($key === 'to' && $tab === 'unclear')
                <label class="block"><span class="sr-only">{{ __('category_review.filters.'.$key) }}</span>
                    <select name="{{ $key }}" class="select">
                        <option value="">{{ __('category_review.filters.'.$key) }}: {{ __('category_review.filters.any') }}</option>
                        @foreach($categories as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach($group['options'] as $option)<option value="{{ $option['id'] }}" @selected($filters[$key] === $option['id'])>{{ $option['name'] }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <div class="flex gap-2">
                <button class="btn btn-primary"><x-icon name="list-filter" class="size-4" />{{ __('category_review.filters.apply') }}</button>
                @if($filtered)<a href="{{ route('admin.category-review.index', ['tab' => $tab]) }}" class="btn btn-ghost">{{ __('category_review.filters.clear') }}</a>@endif
            </div>
        </form>

        @if($items->isEmpty())
            <div class="p-5">
                <x-empty-state :icon="$tab === 'applied' ? 'history' : 'layers'" :title="$filtered ? __('category_review.empty.filtered') : __('category_review.empty.'.$tab)" :text="$filtered ? null : __('category_review.empty.'.$tab.'_text')" />
            </div>
        @else
            <div x-data="{ selected: [], all: @js($items->pluck('id')->all()), toggleAll(e) { this.selected = e.target.checked ? [...this.all] : [] } }">
                @if($tab === 'proposed')
                    <form id="bulk-form" method="POST" action="{{ route('admin.category-review.bulk') }}" data-confirm="{{ __('category_review.confirm_bulk') }}" data-confirm-tone="primary"
                        class="sticky top-16 z-10 flex flex-wrap items-center gap-3 border-b border-navy-50 bg-white/95 px-5 py-3 backdrop-blur">
                        @csrf
                        <label class="inline-flex items-center gap-2 text-sm text-navy-800"><input type="checkbox" class="checkbox" @change="toggleAll($event)" :checked="selected.length === all.length && all.length > 0">{{ __('category_review.select_all') }}</label>
                        <span class="text-xs text-muted-strong" x-show="selected.length" x-cloak x-text="@js(__('category_review.selected', ['count' => '__N__'])).replace('__N__', selected.length)"></span>
                        <div class="ms-auto flex gap-2" x-show="selected.length" x-cloak>
                            <button name="action" value="keep" class="btn btn-outline btn-sm" @click="$el.form.dataset.confirmed = '1'">{{ __('category_review.keep_selected') }}</button>
                            <button name="action" value="apply" class="btn btn-primary btn-sm"><x-icon name="check" class="size-4" />{{ __('category_review.apply_selected') }}</button>
                        </div>
                    </form>
                @endif

                <ul class="divide-y divide-navy-50">
                    @foreach($items as $review)
                        @php
                            $business = $review->business;
                            $level = $review->level();
                            $secondary = collect((array) $review->secondary_ids)->map(fn ($id) => $secondaryCategories->get($id))->filter();
                        @endphp
                        <li class="grid gap-4 px-5 py-4 transition lg:grid-cols-[auto_minmax(0,1.1fr)_minmax(0,1.2fr)_minmax(0,1.2fr)_auto] lg:items-start"
                            x-data="categoryRow" x-show="!gone" x-transition.opacity.duration.300ms>
                            @if($tab === 'proposed')
                                <input type="checkbox" form="bulk-form" name="ids[]" value="{{ $review->id }}" x-model.number="selected" class="checkbox mt-1 hidden lg:block" aria-label="{{ $business->t('name') }}">
                            @else
                                <span class="hidden lg:block"></span>
                            @endif

                            {{-- Business --}}
                            <div class="min-w-0">
                                <a href="{{ route('admin.businesses.show', $business->id) }}" class="block truncate font-semibold text-navy-900 hover:text-gold-700">{{ $business->name_en ?: $business->name_ar }}</a>
                                @if($business->name_en && $business->name_ar)<span class="block truncate text-xs text-muted-strong" dir="rtl" lang="ar">{{ $business->name_ar }}</span>@endif
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @if($review->kind === 'refine')<span class="badge badge-info">{{ __('category_review.kinds.refine') }}</span>@elseif($review->suggested_category_id && $tab === 'proposed')<span class="badge badge-neutral">{{ __('category_review.kinds.fix') }}</span>@endif
                                    @if($review->method === 'ai')<span class="badge badge-gold"><x-icon name="sparkles" class="size-3" />AI</span>@endif
                                </div>
                            </div>

                            {{-- Change --}}
                            <div class="min-w-0 text-sm">
                                <p class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-lg bg-navy-50 px-2 py-1 text-xs text-muted-strong {{ $tab === 'proposed' ? 'line-through decoration-navy-300' : '' }}" title="{{ $path($review->current) }}">{{ $review->current?->t('name') ?? __('category_review.moves.nowhere') }}</span>
                                    @if($review->suggested)
                                        <x-icon name="arrow-right" class="size-3.5 text-gold-600 rtl:-scale-x-100" />
                                        <span class="rounded-lg bg-gold-50 px-2 py-1 text-xs font-semibold text-navy-900 ring-1 ring-gold-200" title="{{ $path($review->suggested) }}">{{ $review->suggested->t('name') }}</span>
                                    @endif
                                </p>
                                @if($secondary->isNotEmpty())<p class="mt-1.5 text-xs text-muted-strong">{{ __('category_review.also', ['categories' => $secondary->map->t('name')->implode('، ')]) }}</p>@endif
                            </div>

                            {{-- Why --}}
                            <ul class="min-w-0 space-y-1.5 text-xs leading-5 text-charcoal">
                                @foreach(array_slice($reasonsFor($review), 0, 3) as $reason)
                                    @php $text = $reason['field'] === 'ai' && $isAr && ! empty($reason['text_ar']) ? $reason['text_ar'] : (string) ($reason['text'] ?? ''); @endphp
                                    <li class="flex gap-2">
                                        <x-icon :name="$fieldIcon[$reason['field']] ?? 'info'" @class(['mt-0.5 size-3.5 shrink-0', 'text-gold-600' => $reason['field'] === 'ai', 'text-muted' => $reason['field'] !== 'ai']) />
                                        <span class="min-w-0"><span class="font-medium text-muted-strong">{{ __('category_review.fields.'.$reason['field']) }}:</span>
                                            <span @if(! $isAr || $reason['field'] !== 'ai') dir="auto" @endif>{!! $highlight($text, $reason['keyword'] ?? null) !!}</span></span>
                                    </li>
                                @endforeach
                                @if(empty($review->reasons))<li class="text-muted">—</li>@endif
                            </ul>

                            {{-- Confidence + actions --}}
                            <div class="flex flex-col gap-3 lg:w-56 lg:items-end">
                                @if($review->suggested_category_id && $tab === 'proposed')
                                    <div class="flex w-full items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ $levelTone[$level] }}" style="width: {{ $review->confidence }}%"></div></div>
                                        <span class="badge {{ $levelBadge[$level] }} tabular-nums">{{ $review->confidence }}%</span>
                                    </div>
                                @endif
                                @if(in_array($tab, ['applied', 'kept'], true))
                                    <p class="text-xs text-muted-strong">{{ $review->reviewed_at?->diffForHumans() }} · {{ $review->reviewer ? __('category_review.by', ['name' => $review->reviewer->name]) : __('category_review.automatic') }}</p>
                                @endif
                                @if($review->isPending())
                                    <div class="flex flex-wrap gap-2 lg:justify-end" x-data="{ other: false }">
                                        @if($review->suggested_category_id)
                                            <form method="POST" action="{{ route('admin.category-review.apply', $review) }}" @submit.prevent="act($el)">@csrf
                                                <button class="btn btn-primary btn-sm" :disabled="busy"><x-icon name="check" class="size-3.5" />{{ __('category_review.apply') }}</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.category-review.keep', $review) }}" @submit.prevent="act($el)">@csrf
                                            <button class="btn btn-ghost btn-sm" :disabled="busy">{{ __('category_review.keep') }}</button>
                                        </form>
                                        <button type="button" class="btn btn-outline btn-sm" @click="other = !other" :aria-expanded="other.toString()"><x-icon name="pencil" class="size-3.5" />{{ __('category_review.change') }}</button>
                                        <form x-show="other" x-collapse x-cloak method="POST" action="{{ route('admin.category-review.apply', $review) }}" @submit.prevent="act($el)" class="flex w-full gap-2">@csrf
                                            <select name="category_id" required class="select min-w-0 flex-1 py-1.5 text-xs" aria-label="{{ __('category_review.choose') }}">
                                                <option value="">{{ __('category_review.choose') }}</option>
                                                @foreach($categories as $group)
                                                    <optgroup label="{{ $group['label'] }}">
                                                        @foreach($group['options'] as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-primary btn-sm" :disabled="busy">{{ __('category_review.move') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if($items->hasPages())<div class="border-t border-navy-50 px-5 py-4">{{ $items->links() }}</div>@endif
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
