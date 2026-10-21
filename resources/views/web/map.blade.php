@php
    $config = [
        'dataUrl' => route('map.data'),
        'profileBase' => route('business.show', ['business' => '__SLUG__']),
        'categories' => (object) [],
        'labels' => ['view' => __('directory.map.view'), 'showing' => __('directory.map.showing', ['count' => ':count'])],
    ];
@endphp
<x-layouts.app>
    @push('head')@vite('resources/js/map.js')@endpush
    <x-page-hero :title="__('directory.map.title')" :subtitle="__('directory.map.subtitle')" icon="map-pinned"
        :breadcrumbs="[['label' => __('directory.map.nav')]]" />

    <section class="container-app relative z-10 -mt-8 pb-16 sm:-mt-10" data-dq-map data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
        <div class="card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-navy-50 p-4 lg:flex-row lg:items-center">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">{{ __('directory.map.search') }}</span>
                    <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
                    <input type="search" data-map-search class="input ps-10" placeholder="{{ __('directory.map.search') }}" autocomplete="off">
                </label>
                <label class="lg:w-72">
                    <span class="sr-only">{{ __('directory.map.all_categories') }}</span>
                    <select data-map-category class="select">
                        <option value="">{{ __('directory.map.all_categories') }}</option>
                        @foreach($roots as $root)
                            <option value="{{ $root->id }}">{{ $root->t('name') }} ({{ format_number($counts[$root->id] ?? 0) }})</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex gap-2">
                    <button type="button" data-map-locate class="btn btn-outline"><x-icon name="locate" class="size-4" />{{ __('directory.map.locate') }}</button>
                    <button type="button" data-map-reset class="btn btn-ghost" title="{{ __('directory.map.reset') }}"><x-icon name="rotate-ccw" class="size-4" /><span class="sr-only">{{ __('directory.map.reset') }}</span></button>
                </div>
            </div>
            <div class="relative">
                <div data-map-canvas class="h-[68vh] min-h-[420px] w-full" role="region" aria-label="{{ __('directory.map.title') }}"></div>
                <div data-map-loading class="absolute inset-0 z-[500] flex items-center justify-center bg-white/70 backdrop-blur-sm">
                    <span class="inline-flex items-center gap-2 rounded-full bg-navy-900 px-4 py-2 text-sm font-medium text-gold-200 shadow-lift"><x-icon name="loader-circle" class="size-4 animate-spin" />{{ __('directory.map.loading') }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-2 border-t border-navy-50 px-4 py-3 text-xs text-muted-strong sm:flex-row sm:items-center sm:justify-between">
                <span class="font-semibold text-navy-900" data-map-count>{{ __('directory.map.showing', ['count' => format_number($total)]) }}</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="info" class="size-3.5 text-gold-600" />{{ __('directory.map.note') }}</span>
            </div>
        </div>
    </section>
</x-layouts.app>
