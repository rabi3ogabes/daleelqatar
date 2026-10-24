{{-- Bulk review of suggested Arabic names. Expects $items (paginator of suggestions), $nameFilters, $sourceTone. --}}
@php
    $rows = $items->getCollection()->map(fn ($s) => [
        'id' => $s->id,
        'suggested' => (string) ($s->fields['name_ar']['value'] ?? ''),
        'en' => (string) $s->business?->name_en,
    ])->values();
    $cfg = [
        'url' => route('admin.enrichment.names'),
        'total' => $items->total(),
        'rows' => $rows,
        'filters' => ['q' => $nameFilters['q'] ?: null, 'source' => $nameFilters['source']],
        'i18n' => [
            'confirmTitle' => __('enrichment.names.confirm_title'),
            'approveAll' => __('enrichment.names.confirm_approve'),
            'rejectAll' => __('enrichment.names.confirm_reject'),
            'approveLabel' => __('enrichment.names.approve'),
            'rejectLabel' => __('enrichment.names.reject'),
            'notArabic' => __('enrichment.names.not_arabic'),
            'doneApprove' => __('enrichment.names.done_approve'),
            'doneReject' => __('enrichment.names.done_reject'),
            'failed' => __('enrichment.names.failed_some'),
            'error' => __('enrichment.names.error'),
        ],
    ];
    $hasArabic = fn ($v) => \App\Support\Import\Normalizer::hasArabic((string) $v);
@endphp
<div x-data="nameReview(@js($cfg))" @keydown.window.escape="busy || clear()">
    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.enrichment.index') }}" class="flex flex-wrap items-end gap-3 border-b border-navy-50 p-4">
        <input type="hidden" name="tab" value="names">
        <x-form.input name="q" :value="$nameFilters['q']" :label="__('common.search')" icon="search" class="w-full sm:w-72" :placeholder="__('enrichment.names.search_placeholder')" />
        <x-form.select name="source" :label="__('enrichment.names.source')" :value="$nameFilters['source']" :placeholder="__('common.all')"
            :options="collect(\App\Support\Enrichment\NameReview::SOURCES)->mapWithKeys(fn ($s) => [$s => __('enrichment.sources.'.$s)])->all()" />
        <x-form.select name="per_page" :label="__('enrichment.names.per_page')" :value="$nameFilters['per_page']"
            :options="collect(\App\Http\Controllers\Admin\EnrichmentController::NAME_PAGE_SIZES)->mapWithKeys(fn ($n) => [$n => $n])->all()" />
        <button class="btn btn-primary" data-no-busy><x-icon name="filter" class="size-4" />{{ __('common.apply') }}</button>
        @if($nameFilters['q'] || $nameFilters['source'])<a href="{{ route('admin.enrichment.index', ['tab' => 'names']) }}" class="btn btn-ghost">{{ __('common.reset') }}</a>@endif
        <p class="ms-auto self-center text-xs text-muted-strong">{{ __('enrichment.names.hint') }}</p>
    </form>

    @if($items->isEmpty())
        <div class="p-5"><x-empty-state icon="languages" :title="__('enrichment.empty.names')" :text="$nameFilters['q'] || $nameFilters['source'] ? __('enrichment.names.no_match') : __('enrichment.empty.names_text')" /></div>
    @else
        {{-- "Select all matching" banner --}}
        <div class="border-b border-gold-200 bg-gold-50/70 px-4 py-2.5 text-sm text-navy-900" x-show="pageAll && cfg.total > cfg.rows.length && !allMatching" x-cloak>
            <span x-text="@js(__('enrichment.names.page_selected')).replace(':count', cfg.rows.length)"></span>
            <button type="button" class="ms-1 font-semibold text-gold-800 underline-offset-2 hover:underline" @click="selectAllMatching()" x-text="@js(__('enrichment.names.select_all')).replace(':count', cfg.total.toLocaleString())"></button>
        </div>
        <div class="border-b border-gold-200 bg-gold-50/70 px-4 py-2.5 text-sm text-navy-900" x-show="allMatching" x-cloak>
            <span class="font-semibold" x-text="@js(__('enrichment.names.all_selected')).replace(':count', count.toLocaleString())"></span>
            <button type="button" class="ms-1 font-semibold text-gold-800 underline-offset-2 hover:underline" @click="clear()">{{ __('enrichment.names.clear') }}</button>
        </div>

        <x-dash.table>
            <thead><tr>
                <th class="w-10">
                    <input type="checkbox" class="checkbox" :checked="pageAll" x-effect="$el.indeterminate = pageSome && !pageAll" @change="togglePage($event.target.checked)" :disabled="busy" aria-label="{{ __('enrichment.names.select_page') }}">
                </th>
                <th class="text-start">{{ __('enrichment.table.business') }}</th>
                <th class="text-start">{{ __('enrichment.names.current') }}</th>
                <th class="min-w-[16rem] text-start">{{ __('enrichment.names.suggested') }}</th>
                <th class="text-start">{{ __('enrichment.names.source') }}</th>
                <th class="text-end"><span class="sr-only">{{ __('common.actions') }}</span></th>
            </tr></thead>
            <tbody>
                @foreach($items as $i => $s)
                    @php $source = $s->fields['name_ar']['source'] ?? 'ai'; $current = $s->business?->name_ar; @endphp
                    <tr class="transition" :class="rowClass({{ $s->id }})">
                        <td>
                            <input type="checkbox" class="checkbox" :checked="isSelected({{ $s->id }})" @click="toggle({{ $s->id }}, {{ $i }}, $event)" :disabled="busy || state[{{ $s->id }}] === 'done'" aria-label="{{ __('enrichment.names.select_row', ['name' => $s->business?->name_en]) }}">
                        </td>
                        <td class="max-w-[18rem]">
                            <a href="{{ route('admin.enrichment.show', $s) }}" class="font-medium text-navy-900 hover:text-gold-700" dir="ltr">{{ $s->business?->name_en }}</a>
                            <span class="block truncate text-xs text-muted">{{ collect([$s->business?->category?->t('name'), $s->business?->municipality?->t('name')])->filter()->implode(' · ') }}</span>
                        </td>
                        <td class="max-w-[12rem] text-sm" dir="rtl">
                            @if($current && $hasArabic($current))<span class="text-muted-strong">{{ $current }}</span>@else<span class="text-xs text-muted">{{ __('enrichment.names.none') }}</span>@endif
                        </td>
                        <td>
                            <div class="relative">
                                <input type="text" dir="rtl" lang="ar" maxlength="160" class="input py-2 text-base" :class="inputClass({{ $s->id }})"
                                    value="{{ $s->fields['name_ar']['value'] ?? '' }}" :value="value({{ $s->id }})" @input="edit({{ $s->id }}, $event.target.value)" :disabled="busy || state[{{ $s->id }}] === 'done'"
                                    aria-label="{{ __('enrichment.names.suggested_for', ['name' => $s->business?->name_en]) }}">
                                <button type="button" x-show="edits[{{ $s->id }}] !== undefined" x-cloak @click="resetEdit({{ $s->id }})" class="absolute end-2 top-1/2 -translate-y-1/2 rounded-md px-1.5 py-0.5 text-[0.65rem] font-semibold text-gold-800 hover:bg-gold-100" title="{{ __('enrichment.names.reset_edit') }}">{{ __('enrichment.names.edited') }} ↺</button>
                            </div>
                            <p class="mt-1 text-xs text-danger" x-show="errors[{{ $s->id }}]" x-text="errors[{{ $s->id }}]" x-cloak></p>
                        </td>
                        <td><span class="badge {{ $sourceTone[$source] ?? 'badge-muted' }}">{{ __('enrichment.sources.'.$source) }}</span></td>
                        <td class="whitespace-nowrap text-end">
                            <template x-if="state[{{ $s->id }}] === 'done'"><span class="inline-flex items-center gap-1 text-xs font-semibold" :class="doneAction[{{ $s->id }}] === 'approve' ? 'text-success' : 'text-muted-strong'"><x-icon name="check" class="size-4" /><span x-text="doneAction[{{ $s->id }}] === 'approve' ? @js(__('enrichment.names.approved')) : @js(__('enrichment.names.rejected'))"></span></span></template>
                            <template x-if="state[{{ $s->id }}] !== 'done'">
                                <span class="inline-flex gap-1">
                                    <button type="button" class="btn-icon size-8 text-success hover:bg-success-soft" @click="single('approve', {{ $s->id }})" :disabled="busy" title="{{ __('enrichment.names.approve') }}"><x-icon name="check" class="size-4" /><span class="sr-only">{{ __('enrichment.names.approve') }}</span></button>
                                    <button type="button" class="btn-icon size-8 text-danger hover:bg-danger-soft" @click="single('reject', {{ $s->id }})" :disabled="busy" title="{{ __('enrichment.names.reject') }}"><x-icon name="x" class="size-4" /><span class="sr-only">{{ __('enrichment.names.reject') }}</span></button>
                                    <a href="{{ route('admin.enrichment.show', $s) }}" class="btn-icon size-8 text-muted hover:text-navy-900" title="{{ __('enrichment.review') }}"><x-icon name="external-link" class="size-4" /><span class="sr-only">{{ __('enrichment.review') }}</span></a>
                                </span>
                            </template>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-dash.table>
        <div class="px-5 pb-24">{{ $items->links() }}</div>

        {{-- Floating action bar --}}
        <div class="fixed inset-x-0 bottom-4 z-40 flex justify-center px-4" x-show="count > 0 || busy || summary" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
            <div class="w-full max-w-3xl rounded-2xl bg-navy-900 p-3 text-white shadow-2xl ring-1 ring-gold-400/30 sm:p-4" role="region" aria-label="{{ __('enrichment.names.bar_label') }}" aria-live="polite">
                <template x-if="busy">
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2"><x-icon name="loader-circle" class="size-4 animate-spin text-gold-300" /><span x-text="(action === 'approve' ? @js(__('enrichment.names.approving')) : @js(__('enrichment.names.rejecting')))"></span></span>
                            <span class="tabular-nums text-white/70"><span x-text="progress.processed.toLocaleString()"></span> / <span x-text="progress.total.toLocaleString()"></span></span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600 transition-all duration-300" :style="`width: ${percent}%`"></div></div>
                        <div class="mt-2 flex justify-end"><button type="button" class="text-xs font-semibold text-white/70 hover:text-white" @click="stopping = true" x-show="!stopping">{{ __('enrichment.bulk.stop') }}</button></div>
                    </div>
                </template>
                <template x-if="!busy && summary">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2"><x-icon name="circle-check" class="size-5 text-gold-300" /><span x-text="summary"></span></span>
                        <span class="flex gap-2">
                            <button type="button" class="btn btn-gold btn-sm" @click="window.location.reload()">{{ __('enrichment.names.refresh') }}</button>
                            <button type="button" class="btn btn-ghost btn-sm !text-white/80" @click="summary = ''">{{ __('common.close') }}</button>
                        </span>
                    </div>
                </template>
                <template x-if="!busy && !summary">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="flex size-8 items-center justify-center rounded-full bg-gold-400 font-bold tabular-nums text-navy-950" x-text="count > 999 ? '999+' : count"></span>
                            <span><span class="font-semibold" x-text="@js(__('enrichment.names.selected')).replace(':count', count.toLocaleString())"></span>
                                <button type="button" class="ms-2 text-xs text-white/60 hover:text-white" @click="clear()">{{ __('enrichment.names.clear') }}</button></span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-sm border border-white/20 !text-white hover:bg-white/10" @click="bulk('reject')"><x-icon name="x" class="size-4" />{{ __('enrichment.names.reject') }}</button>
                            <button type="button" class="btn btn-gold btn-sm" @click="bulk('approve')"><x-icon name="check" class="size-4" />{{ __('enrichment.names.approve') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endif
</div>
