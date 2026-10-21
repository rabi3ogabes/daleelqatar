@php
    $columns = (array) $batch->columns;
    $samples = (array) data_get($batch->options, 'samples', []);
    $letter = function (int $i): string { $s = ''; for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) { $s = chr(65 + ($n - 1) % 26).$s; } return $s; };
    $columnOptions = collect($columns)->mapWithKeys(fn ($h, $i) => [$i => $letter($i).' · '.($h !== '' ? \Illuminate\Support\Str::limit($h, 40) : '—')])->all();
    $reason = function (?string $message): string {
        $key = 'imports.reasons.'.\Illuminate\Support\Str::before((string) $message, ':');
        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : \Illuminate\Support\Str::limit((string) $message, 90);
    };
    $running = in_array($batch->status, ['staging', 'processing'], true);
    $progress = [
        'status' => $batch->status, 'status_label' => __('imports.status.'.$batch->status),
        'total' => $batch->total_rows, 'processed' => $batch->processed_rows, 'created' => $batch->created_count,
        'merged' => $batch->merged_count, 'skipped' => $batch->skipped_count, 'failed' => $batch->failed_count,
        'percent' => $batch->progress(), 'done' => $batch->isFinished(), 'redirect' => route('admin.imports.show', $batch),
    ];
@endphp
<x-layouts.dashboard area="admin" :title="$batch->source_name" :subtitle="$batch->original_name" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('imports.title'), 'url' => route('admin.imports.index')], ['label' => $batch->source_name]]">
    <x-flash class="mb-5" />

    @if($batch->status === 'failed')
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-danger/20 bg-danger-soft p-5 text-sm text-danger">
            <x-icon name="circle-alert" class="mt-0.5 size-5 shrink-0" />
            <div><p class="font-semibold">{{ __('imports.failed_title') }}</p><p class="mt-1">{{ $batch->error }}</p></div>
        </div>
    @elseif($batch->status === 'uploaded')
        <x-empty-state icon="upload" :title="__('imports.waiting_upload')" :text="__('imports.waiting_upload_text')" />
    @endif

    {{-- Step 2: review the detected columns --}}
    @if($batch->status === 'ready')
        <form method="POST" action="{{ route('admin.imports.start', $batch) }}"
            x-data="importRunner(@js(route('admin.imports.process', $batch)), @js($progress), @js(['columns' => $columns, 'samples' => $samples, 'mapping' => (object) $batch->mapping, 'labels' => $fields]))"
            @submit.prevent="start($el)">
            @csrf
            <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4 text-sm text-gold-900">
                <x-icon name="sparkles" class="size-5 text-gold-600" />
                <span>{{ __('imports.detected', ['sheet' => $batch->sheet, 'row' => $batch->header_row, 'count' => count((array) $batch->mapping)]) }}</span>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <x-dash.panel :title="__('imports.mapping_title')" :subtitle="__('imports.mapping_subtitle')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($fields as $field => $label)
                            <div>
                                <label for="map-{{ $field }}" class="label">{{ $label }}@if($field === 'name')<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
                                <select id="map-{{ $field }}" name="mapping[{{ $field }}]" class="select" x-model="mapping.{{ $field }}" @if($field === 'name') required @endif>
                                    <option value="">{{ __('imports.not_used') }}</option>
                                    @foreach($columnOptions as $i => $optLabel)
                                        <option value="{{ $i }}" @selected(($batch->mapping[$field] ?? null) === $i)>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </x-dash.panel>

                <div class="space-y-6">
                    <x-dash.panel :title="__('imports.options_title')">
                        <div class="space-y-4">
                            <x-form.input name="source_name" :label="__('imports.source_name')" :value="$batch->source_name" required maxlength="80" />
                            <x-form.toggle name="publish" :label="__('imports.publish')" :hint="__('imports.publish_hint')" :checked="(bool) $batch->option('publish', true)" />
                            <x-form.toggle name="update_existing" :label="__('imports.update_existing')" :hint="__('imports.update_existing_hint')" :checked="(bool) $batch->option('update_existing', true)" />
                            <div>
                                <label for="default-cat" class="label">{{ __('imports.default_category') }}</label>
                                <select id="default-cat" name="default_category_id" class="select">
                                    <option value="">{{ __('imports.auto_category') }}</option>
                                    @foreach($categories as $parent)
                                        <optgroup label="{{ $parent->t('name') }}">
                                            <option value="{{ $parent->id }}">{{ $parent->t('name') }}</option>
                                            @foreach($parent->children as $child)<option value="{{ $child->id }}">— {{ $child->t('name') }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-muted-strong">{{ __('imports.default_category_hint') }}</p>
                            </div>
                        </div>
                    </x-dash.panel>
                    <div class="card space-y-3 p-5">
                        <p class="flex items-start gap-2 text-xs leading-6 text-muted-strong"><x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-gold-600" />{{ __('imports.dedupe_note') }}</p>
                        <p x-show="error" x-cloak class="rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" x-text="error"></p>
                        <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="busy" data-no-busy>
                            <x-icon name="play" class="size-4" /><span x-text="busy ? @js(__('imports.preparing')) : @js(__('imports.start'))"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Live preview of the first rows with the chosen mapping --}}
            <x-dash.panel class="mt-6" :title="__('imports.preview_title')" :subtitle="__('imports.preview_subtitle')" :padding="false">
                <x-dash.table>
                    <thead>
                        <tr>
                            <template x-for="f in previewFields" :key="f.key"><th class="text-start whitespace-nowrap" x-text="f.label"></th></template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, r) in samples" :key="r">
                            <tr>
                                <template x-for="f in previewFields" :key="f.key"><td class="max-w-56 truncate text-xs" dir="auto" x-text="cell(row, f.key) || '—'"></td></template>
                            </tr>
                        </template>
                    </tbody>
                </x-dash.table>
            </x-dash.panel>
        </form>
    @endif

    {{-- Step 3: progress --}}
    @if($running)
        <div x-data="importRunner(@js(route('admin.imports.process', $batch)), @js($progress))" x-init="run()" class="space-y-6">
            <x-dash.panel :title="__('imports.running_title')" :subtitle="__('imports.running_subtitle')">
                <div class="flex items-end justify-between gap-4">
                    <p class="text-4xl font-bold text-navy-900 tabular-nums" x-text="`${p.percent}%`"></p>
                    <p class="text-sm text-muted-strong" x-text="`${p.processed.toLocaleString()} / ${p.total.toLocaleString()}`"></p>
                </div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full bg-gradient-to-r from-gold-500 to-gold-400 transition-all duration-500" :style="`width: ${p.percent}%`"></div></div>
                <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach(['created' => 'text-success', 'merged' => 'text-navy-900', 'skipped' => 'text-muted-strong', 'failed' => 'text-danger'] as $k => $tone)
                        <div class="rounded-xl bg-navy-50/60 p-3"><dt class="text-xs text-muted-strong">{{ __('imports.'.$k) }}</dt><dd class="mt-1 text-xl font-bold tabular-nums {{ $tone }}" x-text="p.{{ $k }}.toLocaleString()"></dd></div>
                    @endforeach
                </dl>
                <p x-show="error" x-cloak class="mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" x-text="error"></p>
                <p class="mt-4 flex items-center gap-2 text-xs text-muted-strong"><x-icon name="info" class="size-4 text-gold-600" />{{ __('imports.keep_open') }}</p>
            </x-dash.panel>
            <form method="POST" action="{{ route('admin.imports.cancel', $batch) }}" data-confirm="{{ __('imports.cancel_confirm') }}">
                @csrf
                <button class="btn btn-danger-outline btn-sm"><x-icon name="circle-x" class="size-4" />{{ __('imports.cancel') }}</button>
            </form>
        </div>
    @endif

    {{-- Step 4: report --}}
    @if(in_array($batch->status, ['completed', 'cancelled'], true))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-dash.stat :label="__('imports.rows')" :value="$batch->total_rows" icon="file-spreadsheet" />
            <x-dash.stat :label="__('imports.created')" :value="$batch->created_count" icon="plus" :href="route('admin.businesses.index')" />
            <x-dash.stat :label="__('imports.merged')" :value="$batch->merged_count" icon="layers" />
            <x-dash.stat :label="__('imports.skipped')" :value="$batch->skipped_count" icon="copy" />
            <x-dash.stat :label="__('imports.failed')" :value="$batch->failed_count" icon="circle-alert" />
        </div>
        <p class="mt-4 text-sm text-muted-strong">
            {{ __('imports.summary', ['time' => $batch->started_at && $batch->finished_at ? $batch->started_at->diffForHumans($batch->finished_at, true) : '—']) }}
        </p>

        <x-dash.panel class="mt-6" :title="__('imports.issues_title', ['count' => format_number($issueCount)])" :subtitle="__('imports.issues_subtitle')" :padding="false">
            <x-slot:actions>
                <a href="{{ route('admin.imports.report', $batch) }}" class="btn btn-outline btn-sm"><x-icon name="download" class="size-4" />{{ __('imports.download_report') }}</a>
            </x-slot:actions>
            @if($issues->isEmpty())
                <div class="p-5"><x-empty-state icon="circle-check" :title="__('imports.no_issues')" /></div>
            @else
                <x-dash.table>
                    <thead><tr><th class="text-start">#</th><th class="text-start">{{ __('imports.company') }}</th><th class="text-start">{{ __('imports.reason') }}</th><th class="text-start">{{ __('imports.listing') }}</th></tr></thead>
                    <tbody>
                        @foreach($issues as $row)
                            <tr>
                                <td class="tabular-nums text-muted">{{ $row->row_number }}</td>
                                <td dir="auto">{{ $row->payload['name_en'] ?? $row->payload['name_ar'] ?? '—' }}</td>
                                <td class="text-xs text-muted-strong">{{ $reason($row->message) }}</td>
                                <td>@if($row->business_id)<a href="{{ route('admin.businesses.show', $row->business_id) }}" class="text-gold-700 hover:underline">#{{ $row->business_id }}</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-dash.table>
            @endif
        </x-dash.panel>
    @endif

    @if($batch->isFinished() || $batch->status === 'ready' || $batch->status === 'uploaded')
        <form method="POST" action="{{ route('admin.imports.destroy', $batch) }}" class="mt-6" data-confirm="{{ __('imports.delete_confirm') }}">
            @csrf @method('DELETE')
            <button class="btn btn-ghost btn-sm text-danger"><x-icon name="trash-2" class="size-4" />{{ __('imports.delete') }}</button>
        </form>
    @endif
</x-layouts.dashboard>
