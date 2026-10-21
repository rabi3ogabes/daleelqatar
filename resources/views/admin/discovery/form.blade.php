@php
    $editing = $source->exists;
    $title = $editing ? $source->name : __('discovery.create_title');
    $settings = (array) $source->settings;
    $fieldLabels = collect(['name_en', 'name_ar', 'categories', 'phone', 'email', 'website', 'social', 'address', 'city', 'coordinates', 'hours', 'description'])
        ->mapWithKeys(fn ($f) => [$f => __('discovery.result_fields.'.$f)])->all();
@endphp
<x-layouts.dashboard area="admin" :title="$title" :subtitle="__('discovery.form_subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('discovery.title'), 'url' => route('admin.discovery.index')], ['label' => $title]]">
    <x-flash class="mb-5" />

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
                                        <dd class="break-words font-medium text-navy-900" dir="auto" x-text="value"></dd>
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
