@php
    $sites = app(\App\Support\Enrichment\WebsiteReader::class)->candidates($business);
    $domains = collect($sites)->map(fn ($s) => \App\Support\Import\Normalizer::domain($s['url']))->filter()->unique()->implode('، ');
    $config = [
        'findUrl' => route('admin.businesses.logo.find', $business->id),
        'applyUrl' => route('admin.businesses.logo.apply', $business->id),
        'nameEn' => $business->name_en,
        'nameAr' => $business->name_ar,
        'i18n' => ['failed' => __('logos.failed')],
    ];
@endphp
<x-dash.panel :title="__('logos.panel_title')" :subtitle="__('logos.panel_subtitle')" x-data="logoStudio({{ \Illuminate\Support\Js::from($config) }})">
    <div class="flex items-center gap-4">
        @if($business->logoUrl())
            <img src="{{ $business->logoUrl() }}" alt="{{ $business->t('name') }}" class="size-20 shrink-0 rounded-2xl border border-navy-100 bg-white object-contain p-1 shadow-soft">
        @else
            <span class="flex size-20 shrink-0 flex-col items-center justify-center gap-1 rounded-2xl border border-dashed border-navy-200 bg-navy-50/60 text-center text-[11px] leading-tight text-muted-strong">
                <x-icon name="image" class="size-5 text-navy-300" />{{ __('logos.no_logo') }}
            </span>
        @endif
        <p class="min-w-0 text-xs leading-5 text-muted-strong">
            @if($domains !== '')
                {{ __('logos.looks_at', ['domains' => '']) }}<span class="font-medium text-navy-900" dir="ltr">{{ $domains }}</span>
            @else
                {{ __('logos.no_site_hint') }}
            @endif
        </p>
    </div>
    <div class="mt-4 grid gap-2">
        <button type="button" class="btn btn-primary w-full" @click="show('website')" @disabled($sites === [])><x-icon name="scan-search" class="size-4" />{{ __('logos.find') }}</button>
        <button type="button" class="btn btn-gold w-full" @click="show('design')"><x-icon name="sparkles" class="size-4" />{{ __('logos.design') }}</button>
        <form method="POST" action="{{ route('admin.businesses.logo', $business->id) }}" enctype="multipart/form-data" x-data="{ name: '' }" class="flex items-center gap-2">
            @csrf
            <label class="btn btn-outline btn-sm flex-1 cursor-pointer">
                <x-icon name="upload" class="size-4" /><span x-text="name || @js(__('logos.upload'))" class="truncate"></span>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" required @change="name = $event.target.files[0]?.name || ''">
            </label>
            <button class="btn btn-outline btn-sm" x-show="name" x-cloak><x-icon name="check" class="size-4" /></button>
        </form>
        @error('logo')<p class="error">{{ $message }}</p>@enderror
    </div>

    {{-- Studio --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="logo-studio-title" @keydown.escape.window="close()">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="close()"></div>
            <div x-show="open" x-transition x-trap.noscroll="open" class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-lift sm:rounded-3xl">
                <header class="flex items-start gap-4 border-b border-navy-50 px-6 pt-5 pb-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-gold-300"><x-icon name="image" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">{{ __('logos.studio') }}</p>
                        <h2 id="logo-studio-title" class="mt-0.5 truncate text-lg font-semibold text-navy-900">{{ $business->t('name') }}</h2>
                    </div>
                    <button type="button" class="btn-icon text-muted-strong hover:bg-navy-50" @click="close()" aria-label="{{ __('logos.close') }}"><x-icon name="x" class="size-5" /></button>
                </header>

                <div class="flex gap-1 border-b border-navy-50 px-6" role="tablist">
                    @foreach(['website' => ['scan-search', 'logos.tab_website'], 'design' => ['sparkles', 'logos.tab_design']] as $key => [$icon, $label])
                        <button type="button" role="tab" :aria-selected="(tab === '{{ $key }}').toString()" @click="switchTab('{{ $key }}')"
                            class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-semibold transition"
                            :class="tab === '{{ $key }}' ? 'border-gold-500 text-navy-900' : 'border-transparent text-muted-strong hover:text-navy-900'"
                            @if($key === 'website' && $sites === []) disabled @endif>
                            <x-icon :name="$icon" class="size-4" />{{ __($label) }}
                        </button>
                    @endforeach
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    {{-- From the website --}}
                    <div x-show="tab === 'website'">
                        <div x-show="searching" class="py-6">
                            <p class="flex items-center gap-2 text-sm text-muted-strong" aria-live="polite">
                                <x-icon name="loader-circle" class="size-4 animate-spin text-gold-600" />
                                <span>{{ $domains !== '' ? __('logos.searching', ['domain' => \Illuminate\Support\Str::before($domains, '،')]) : __('logos.searching_generic') }}</span>
                            </p>
                            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                                @for($i = 0; $i < 3; $i++)<div class="aspect-square animate-pulse rounded-2xl bg-navy-50"></div>@endfor
                            </div>
                        </div>

                        <div x-show="!searching && searched" x-cloak>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="text-muted-strong">{{ __('logos.checked') }}</span>
                                <template x-for="site in sites" :key="site.url">
                                    <span class="badge" :class="site.reachable ? 'badge-neutral' : 'badge-warning'">
                                        <span dir="ltr" x-text="site.url.replace(/^https?:\/\//, '').replace(/\/$/, '')"></span>
                                        <span class="text-muted" x-text="'· ' + ({{ \Illuminate\Support\Js::from(__('logos.site_source')) }}[site.source] || site.source) + (site.reachable ? '' : ' · ' + @js(__('logos.unreachable_site')))"></span>
                                    </span>
                                </template>
                                <button type="button" class="ms-auto btn btn-ghost btn-sm" @click="search()"><x-icon name="refresh-cw" class="size-3.5" />{{ __('logos.search_again') }}</button>
                            </div>

                            <p class="mt-4 text-sm" :class="candidates.length ? 'text-navy-900' : 'text-muted-strong'" x-text="message" aria-live="polite"></p>

                            <div x-show="candidates.length" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <template x-for="c in candidates" :key="c.id">
                                    <button type="button" @click="selected = { type: 'candidate', id: c.id }" :aria-pressed="isSelected('candidate', c.id).toString()"
                                        class="group relative overflow-hidden rounded-2xl border-2 bg-white text-start transition"
                                        :class="isSelected('candidate', c.id) ? 'border-gold-500 shadow-gold' : 'border-navy-100 hover:border-navy-200'">
                                        <span class="bg-checker block aspect-square p-3">
                                            <img :src="c.preview" alt="" class="size-full object-contain">
                                        </span>
                                        <span class="block border-t border-navy-50 px-3 py-2">
                                            <span class="block truncate text-xs font-semibold text-navy-900" x-text="{{ \Illuminate\Support\Js::from(__('logos.sources')) }}[c.source] || c.source"></span>
                                            <span class="block truncate text-[11px] text-muted" dir="ltr" x-text="c.kind === 'svg' ? @js(__('logos.vector')) + ' · SVG' : c.width + ' × ' + c.height + ' px'"></span>
                                        </span>
                                        <span x-show="isSelected('candidate', c.id)" class="absolute end-2 top-2 flex size-6 items-center justify-center rounded-full bg-gold-500 text-navy-950 shadow"><x-icon name="check" class="size-3.5" /></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="candidates.some((c) => c.dark)" class="mt-3 text-xs text-muted-strong">{{ __('logos.dark_plate') }}</p>

                            <div x-show="!candidates.length && !error" class="mt-6 rounded-2xl border border-dashed border-navy-200 bg-navy-50/50 p-6 text-center">
                                <x-icon name="image" class="mx-auto size-8 text-navy-300" />
                                <button type="button" class="btn btn-gold mt-4" @click="switchTab('design')"><x-icon name="sparkles" class="size-4" />{{ __('logos.design_instead') }}</button>
                            </div>
                        </div>
                    </div>

                    {{-- Branded design --}}
                    <div x-show="tab === 'design'" x-cloak>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <template x-for="style in styles" :key="style">
                                <button type="button" @click="selected = { type: 'style', id: style }" :aria-pressed="isSelected('style', style).toString()"
                                    class="group relative overflow-hidden rounded-2xl border-2 bg-white text-start transition"
                                    :class="isSelected('style', style) ? 'border-gold-500 shadow-gold' : 'border-navy-100 hover:border-navy-200'">
                                    <span class="block aspect-square bg-navy-50">
                                        <img x-show="previews[style]" :src="previews[style]" alt="" class="size-full">
                                    </span>
                                    <span class="block border-t border-navy-50 px-3 py-2 text-xs font-semibold text-navy-900" x-text="{{ \Illuminate\Support\Js::from(__('logos.styles')) }}[style]"></span>
                                    <span x-show="isSelected('style', style)" class="absolute end-2 top-2 flex size-6 items-center justify-center rounded-full bg-gold-500 text-navy-950 shadow"><x-icon name="check" class="size-3.5" /></span>
                                </button>
                            </template>
                        </div>
                        <div class="mt-5 grid gap-4 sm:grid-cols-[10rem_1fr] sm:items-start">
                            <div>
                                <label for="logo-initials" class="label">{{ __('logos.initials') }}</label>
                                <input id="logo-initials" type="text" maxlength="3" x-model="initials" class="input text-center font-semibold tracking-widest" autocomplete="off">
                                <p class="hint">{{ __('logos.initials_hint') }}</p>
                            </div>
                            <div class="space-y-3 text-sm">
                                @if($business->name_en && $business->name_ar)
                                    <label class="flex items-center gap-2 font-medium text-navy-900"><input type="checkbox" class="checkbox" x-model="showArabic">{{ __('logos.show_arabic') }}</label>
                                @endif
                                <p class="flex gap-2 rounded-xl bg-gold-50/70 px-3 py-2.5 text-xs leading-5 text-gold-900"><x-icon name="info" class="mt-0.5 size-4 shrink-0" />{{ __('logos.name_as_is') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="flex flex-col-reverse gap-3 border-t border-navy-50 bg-navy-50/40 px-6 py-4 sm:flex-row sm:items-center">
                    <p class="min-w-0 flex-1 text-xs" :class="error ? 'font-medium text-danger' : 'text-muted-strong'" x-text="error || (selected ? '' : @js(__('logos.pick_one')))" aria-live="polite"></p>
                    <button type="button" class="btn btn-outline" @click="close()" :disabled="saving">{{ __('logos.cancel') }}</button>
                    <button type="button" class="btn btn-primary" @click="save()" :disabled="!selected || saving">
                        <x-icon name="loader-circle" class="size-4 animate-spin" x-show="saving" x-cloak />
                        <x-icon name="check" class="size-4" x-show="!saving" />
                        <span x-text="saving ? @js(__('logos.saving')) : @js(__('logos.use'))"></span>
                    </button>
                </footer>
            </div>
        </div>
    </template>
</x-dash.panel>
