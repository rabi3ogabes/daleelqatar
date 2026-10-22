@php
    $editing = $source->exists;
    $title = $editing ? $source->name : __('discovery.create_title');
    $settings = (array) $source->settings;
    $fieldLabels = collect(['name_en', 'name_ar', 'categories', 'phone', 'email', 'website', 'social', 'address', 'city', 'coordinates', 'hours', 'description', 'logo', 'image'])
        ->mapWithKeys(fn ($f) => [$f => __('discovery.result_fields.'.$f)])->all();
@endphp
<x-layouts.dashboard area="admin" :title="$title" :subtitle="__('discovery.form_subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('discovery.title'), 'url' => route('admin.discovery.index')], ['label' => $title]]">
    <x-flash class="mb-5" />

    @if($preset ?? false)
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4 text-sm text-gold-900">
            <x-icon name="sparkles" class="mt-0.5 size-5 shrink-0 text-gold-600" />
            <p class="leading-6">{{ __('discovery.preset_loaded') }}</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <form method="POST" action="{{ $editing ? route('admin.discovery.update', $source) : route('admin.discovery.store') }}" class="min-w-0 space-y-6">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-dash.panel :title="__('discovery.site_title')" :subtitle="__('discovery.site_hint')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-form.input name="name" :label="__('discovery.fields.name')" :value="$source->name" required maxlength="120" />
                    <x-form.input name="base_url" type="url" dir="ltr" :label="__('discovery.fields.base_url')" :value="$source->base_url" :hint="__('discovery.hints.base_url')" required placeholder="https://example.qa" />
                    <x-form.select name="discovery" :label="__('discovery.fields.discovery')" :value="$source->discovery" required
                        :options="collect(\App\Http\Controllers\Admin\DiscoveryController::MODES)->mapWithKeys(fn ($m) => [$m => __('discovery.modes.'.$m)])->all()" :hint="__('discovery.hints.discovery')" />
                    <x-form.input name="url_pattern" dir="ltr" :label="__('discovery.fields.url_pattern')" :value="$source->url_pattern" :hint="__('discovery.hints.url_pattern')" placeholder="/company/[^/]+$" maxlength="500" />
                    <x-form.textarea name="start_urls" dir="ltr" rows="4" class="md:col-span-2" :label="__('discovery.fields.start_urls')" :value="$source->start_urls" :hint="__('discovery.hints.start_urls')" />
                    <x-form.input name="search_url" dir="ltr" class="md:col-span-2" :label="__('discovery.fields.search_url')" :value="$settings['search_url'] ?? null" :hint="__('discovery.hints.search_url')" placeholder="https://example.qa/search?q={query}" maxlength="500" />
                </div>

                <div x-data="sourceAnalyzer(@js(['url' => route('admin.discovery.analyze')]))" class="mt-5 rounded-2xl border border-navy-100 bg-gradient-to-br from-navy-50/70 to-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon name="wand-sparkles" class="size-4.5" /></span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold text-navy-900">{{ __('discovery.analyze_title') }}</p>
                            <p class="text-muted-strong">{{ __('discovery.analyze_hint') }}</p>
                        </div>
                        <button type="button" class="btn btn-outline shrink-0" @click="analyze()" :disabled="busy" data-no-busy>
                            <x-icon name="scan-search" class="size-4" x-show="!busy" /><x-icon name="loader-circle" class="size-4 animate-spin" x-show="busy" x-cloak />
                            <span x-text="busy ? @js(__('discovery.analyzing')) : @js(__('discovery.analyze_cta'))"></span>
                        </button>
                    </div>
                    <p x-show="error" x-cloak class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger" x-text="error"></p>
                    <template x-if="result">
                        <div class="mt-4 space-y-3 border-t border-navy-100 pt-4 text-sm">
                            <ol class="space-y-1.5">
                                <template x-for="step in result.steps" :key="step.label">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-0.5 flex size-4.5 shrink-0 items-center justify-center rounded-full" :class="step.ok ? 'bg-success text-white' : 'bg-navy-100 text-muted'">
                                            <x-icon name="check" class="size-3" x-show="step.ok" /><x-icon name="minus" class="size-3" x-show="!step.ok" />
                                        </span>
                                        <span class="min-w-0"><span class="text-navy-900" x-text="step.label"></span>
                                            <span x-show="step.detail" class="block truncate text-xs text-muted-strong" dir="ltr" x-text="step.detail"></span></span>
                                    </li>
                                </template>
                            </ol>
                            <template x-if="result.ok">
                                <div class="rounded-xl bg-white p-3 ring-1 ring-navy-100">
                                    <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5 text-xs">
                                        <dt class="text-muted-strong">{{ __('discovery.fields.discovery') }}</dt><dd class="font-medium text-navy-900" x-text="result.mode_label"></dd>
                                        <dt class="text-muted-strong">{{ __('discovery.fields.start_urls') }}</dt><dd class="break-all font-mono text-navy-900" dir="ltr" x-text="result.start_urls || '—'"></dd>
                                        <dt class="text-muted-strong">{{ __('discovery.fields.url_pattern') }}</dt><dd class="font-mono text-navy-900" dir="ltr" x-text="result.url_pattern"></dd>
                                        <template x-if="result.last_page"><dt class="text-muted-strong">{{ __('discovery.analyze_pages') }}</dt></template>
                                        <template x-if="result.last_page"><dd class="tabular-nums text-navy-900" x-text="result.last_page.toLocaleString()"></dd></template>
                                    </dl>
                                    <template x-if="result.sample">
                                        <div class="mt-3 flex gap-3 border-t border-navy-50 pt-3">
                                            <template x-if="result.sample.fields.image"><img :src="result.sample.fields.image" alt="" class="size-16 shrink-0 rounded-lg object-cover ring-1 ring-navy-100" referrerpolicy="no-referrer" loading="lazy"></template>
                                            <div class="min-w-0 text-xs">
                                                <p class="font-semibold text-navy-900" x-text="result.sample.fields.name_en"></p>
                                                <p class="text-muted-strong" x-text="[result.sample.fields.categories, result.sample.fields.phone, result.sample.fields.email].filter(Boolean).join(' · ')"></p>
                                                <a :href="result.sample.url" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-gold-700 hover:underline">{{ __('discovery.analyze_sample') }} <x-icon name="external-link" class="size-3" /></a>
                                            </div>
                                        </div>
                                    </template>
                                    <button type="button" class="btn btn-primary btn-sm mt-3 w-full" @click="apply()" data-no-busy>
                                        <x-icon name="check" class="size-3.5" /><span x-text="applied ? @js(__('discovery.analyze_applied')) : @js(__('discovery.analyze_apply'))"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </x-dash.panel>

            <x-dash.panel :title="__('discovery.extract_title')" :subtitle="__('discovery.extract_hint')">
                <x-form.textarea name="selectors" dir="ltr" rows="6" class="font-mono text-xs" :label="__('discovery.fields.selectors')"
                    :value="\App\Support\Discovery\DiscoveryRunner::selectorsText((array) ($source->selectors ?? []))" :hint="__('discovery.hints.selectors')"
                    placeholder="name: h1.company-title&#10;phone: a[href^=tel]@href&#10;address: .address" />
                <p class="mt-3 text-xs text-muted-strong">{{ __('discovery.fields_available') }} <span dir="ltr" class="font-mono">{{ implode(', ', \App\Support\Discovery\Extractor::FIELDS) }}</span></p>
            </x-dash.panel>

            <x-dash.panel :title="__('discovery.behaviour_title')">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-form.input name="delay_ms" type="number" min="1000" max="60000" step="100" :label="__('discovery.fields.delay_ms')" :value="$settings['delay_ms'] ?? 1500" :hint="__('discovery.hints.delay_ms')" required />
                    <x-form.input name="max_pages_per_run" type="number" min="1" max="200" :label="__('discovery.fields.max_pages_per_run')" :value="$settings['max_pages_per_run'] ?? 40" required />
                    <x-form.input name="rediscover_hours" type="number" min="1" max="720" :label="__('discovery.fields.rediscover_hours')" :value="$settings['rediscover_hours'] ?? 24" required />
                </div>
                <div class="mt-5 space-y-4 border-t border-navy-50 pt-5">
                    <x-form.toggle name="publish" :label="__('discovery.fields.publish')" :hint="__('discovery.hints.publish')" :checked="(bool) ($settings['publish'] ?? false)" />
                    <x-form.toggle name="is_enabled" :label="__('discovery.fields.is_enabled')" :hint="__('discovery.hints.is_enabled')" :checked="(bool) $source->is_enabled" />
                </div>
            </x-dash.panel>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.discovery.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <button class="btn btn-primary"><x-icon name="check" class="size-4" />{{ __('common.save_changes') }}</button>
            </div>
        </form>

        <aside class="space-y-6">
            <x-dash.panel :title="__('discovery.test_title')" :subtitle="__('discovery.test_hint')">
                <div x-data="discoveryTester(@js(route('admin.discovery.test')), @js($editing ? $source->id : null), @js($fieldLabels), @js(['failed' => __('discovery.test_failed')]))" class="space-y-3">
                    <label for="test-url" class="label">{{ __('discovery.test_url') }}</label>
                    <input id="test-url" type="url" dir="ltr" class="input" x-model="url" placeholder="https://example.qa/company/..." @keydown.enter.prevent="run()">
                    <button type="button" class="btn btn-outline w-full" @click="run()" :disabled="busy || !url" data-no-busy>
                        <x-icon name="scan-search" class="size-4" x-show="!busy" /><x-icon name="loader-circle" class="size-4 animate-spin" x-show="busy" x-cloak />
                        {{ __('discovery.test_cta') }}
                    </button>
                    <p x-show="error" x-cloak class="rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger" x-text="error"></p>
                    <template x-if="result">
                        <div class="rounded-xl border border-navy-100 bg-navy-50/40 p-3 text-xs">
                            <p class="mb-2 flex items-center justify-between gap-2">
                                <span class="font-semibold text-navy-900">{{ __('discovery.test_result') }}</span>
                                <span class="badge badge-gold" x-text="result.method"></span>
                            </p>
                            <p x-show="result.message" class="text-warning" x-text="result.message"></p>
                            <dl class="space-y-1.5">
                                <template x-for="(value, key) in result.fields" :key="key">
                                    <div class="grid grid-cols-[6.5rem_1fr] gap-2">
                                        <dt class="text-muted-strong" x-text="labels[key] || key"></dt>
                                        <dd class="break-words font-medium text-navy-900" dir="auto">
                                            <template x-if="key === 'logo' || key === 'image'">
                                                <a :href="value" target="_blank" rel="noopener noreferrer"><img :src="value" alt="" class="max-h-28 rounded-lg object-contain ring-1 ring-navy-100" referrerpolicy="no-referrer" loading="lazy"></a>
                                            </template>
                                            <span x-show="key !== 'logo' && key !== 'image'" x-text="value"></span>
                                        </dd>
                                    </div>
                                </template>
                            </dl>
                        </div>
                    </template>
                </div>
            </x-dash.panel>

            <div class="card space-y-3 p-5 text-sm">
                <p class="flex items-center gap-2 font-semibold text-navy-900"><x-icon name="shield-check" class="size-4.5 text-gold-600" />{{ __('discovery.legal_title') }}</p>
                <p class="leading-6 text-muted-strong">{{ __('discovery.legal_text') }}</p>
                <ul class="list-disc space-y-1 ps-5 text-xs text-muted-strong">
                    <li>{{ __('discovery.rules.robots') }}</li>
                    <li>{{ __('discovery.rules.delay') }}</li>
                    <li>{{ __('discovery.rules.ratings') }}</li>
                    <li>{{ __('discovery.rules.duplicates') }}</li>
                    <li>{{ __('discovery.rules.wording') }}</li>
                </ul>
            </div>

            @if($editing)
                <form method="POST" action="{{ route('admin.discovery.destroy', $source) }}" data-confirm="{{ __('discovery.confirm_delete') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-ghost w-full text-danger"><x-icon name="trash-2" class="size-4" />{{ __('discovery.delete') }}</button>
                </form>
            @endif
        </aside>
    </div>

    @push('scripts')
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            document.addEventListener('alpine:init', () => {
                window.Alpine.data('discoveryTester', (endpoint, sourceId, labels, messages) => ({
                    url: '', busy: false, result: null, error: null, labels,
                    async run() {
                        if (!this.url || this.busy) return;
                        this.busy = true; this.error = null; this.result = null;
                        const selectors = document.getElementById('f_selectors');
                        try {
                            const res = await fetch(endpoint, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                                body: JSON.stringify({ url: this.url, source_id: sourceId, selectors: selectors ? selectors.value : null }),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                this.error = data.message || messages.failed;
                            } else {
                                this.result = data;
                            }
                        } catch (e) {
                            this.error = messages.failed;
                        } finally {
                            this.busy = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.dashboard>
