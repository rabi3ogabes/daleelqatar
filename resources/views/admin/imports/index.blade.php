<x-layouts.dashboard area="admin" :title="__('imports.title')" :subtitle="__('imports.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('imports.title')]]">
    <x-flash class="mb-5" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        {{-- Upload --}}
        <x-dash.panel :title="__('imports.upload_title')" :subtitle="__('imports.upload_subtitle', ['mb' => $maxMb])">
            <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data"
                x-data="importUploader(@js(route('admin.imports.sign')), @js($directUpload), {{ $maxMb }}, @js(['too_big' => __('imports.errors.too_big', ['mb' => $maxMb]), 'type' => __('imports.errors.type'), 'failed' => __('imports.errors.upload_failed')]))"
                @submit="submit($event)" class="space-y-5">
                @csrf
                <label for="import-file"
                    @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop($event)"
                    :class="over ? 'border-gold-500 bg-gold-50/70' : 'border-navy-200 bg-navy-50/40 hover:border-gold-400'"
                    class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-navy-900 text-gold-300 shadow-soft"><x-icon name="file-spreadsheet" class="size-7" /></span>
                    <span class="text-base font-semibold text-navy-900" x-text="file ? file.name : @js(__('imports.drop'))"></span>
                    <span class="text-xs text-muted-strong" x-show="!file">{{ __('imports.drop_hint') }}</span>
                    <span class="text-xs text-muted-strong" x-show="file" x-cloak x-text="file ? (file.size / 1048576).toFixed(1) + ' MB' : ''"></span>
                    <input id="import-file" type="file" name="file" accept=".xlsx,.csv,.tsv,.txt" class="sr-only" x-ref="input" @change="pick($event)">
                </label>

                <x-form.input name="source_name" :label="__('imports.source_name')" :hint="__('imports.source_name_hint')" maxlength="80" x-model="source" />

                <p x-show="error" x-cloak class="rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" x-text="error"></p>

                <div x-show="busy" x-cloak class="space-y-2">
                    <div class="h-2 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full bg-gold-500 transition-all" :style="`width: ${progress}%`"></div></div>
                    <p class="text-xs text-muted-strong" x-text="`${@js(__('imports.uploading'))} ${progress}%`"></p>
                </div>

                <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="!file || busy" data-no-busy>
                    <x-icon name="upload" class="size-4" />{{ __('imports.upload_cta') }}
                </button>
            </form>
        </x-dash.panel>

        {{-- How it works --}}
        <x-dash.panel :title="__('imports.how_title')">
            <ol class="space-y-4 text-sm">
                @foreach(['how_1', 'how_2', 'how_3', 'how_4'] as $i => $key)
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gold-50 text-xs font-bold text-gold-800 ring-1 ring-gold-200">{{ $i + 1 }}</span>
                        <span class="text-charcoal">{{ __('imports.'.$key) }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="mt-5 rounded-xl border border-navy-100 bg-navy-50/50 p-4 text-xs leading-6 text-muted-strong">
                <p class="font-semibold text-navy-900">{{ __('imports.columns_title') }}</p>
                <p class="mt-1">{{ __('imports.columns_text') }}</p>
            </div>
        </x-dash.panel>
    </div>

    {{-- History --}}
    <x-dash.panel class="mt-6" :title="__('imports.history')" :padding="false">
        @if($batches->isEmpty())
            <div class="p-5"><x-empty-state icon="file-spreadsheet" :title="__('imports.empty')" :text="__('imports.empty_text')" /></div>
        @else
            <x-dash.table>
                    <thead>
                        <tr>
                            <th class="text-start">{{ __('imports.file') }}</th>
                            <th class="text-start">{{ __('imports.status_label') }}</th>
                            <th class="text-end">{{ __('imports.rows') }}</th>
                            <th class="text-end">{{ __('imports.created') }}</th>
                            <th class="text-end">{{ __('imports.merged') }}</th>
                            <th class="text-end">{{ __('imports.skipped') }}</th>
                            <th class="text-start">{{ __('imports.when') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $b)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.imports.show', $b) }}" class="font-medium text-navy-900 hover:text-gold-700">{{ $b->source_name }}</a>
                                    <span class="block max-w-xs truncate text-xs text-muted" dir="auto">{{ $b->original_name }}</span>
                                </td>
                                <td><span @class(['badge', 'badge-success' => $b->status === 'completed', 'badge-danger' => $b->status === 'failed', 'badge-gold' => in_array($b->status, ['staging', 'processing', 'ready', 'uploaded'], true), 'badge-muted' => $b->status === 'cancelled'])>{{ __('imports.status.'.$b->status) }}</span></td>
                                <td class="text-end tabular-nums">{{ format_number($b->total_rows) }}</td>
                                <td class="text-end tabular-nums text-success">{{ format_number($b->created_count) }}</td>
                                <td class="text-end tabular-nums">{{ format_number($b->merged_count) }}</td>
                                <td class="text-end tabular-nums text-muted-strong">{{ format_number($b->skipped_count + $b->failed_count) }}</td>
                                <td class="whitespace-nowrap text-xs text-muted-strong">{{ $b->created_at?->diffForHumans() }}<span class="block">{{ $b->user?->name }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $batches->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
