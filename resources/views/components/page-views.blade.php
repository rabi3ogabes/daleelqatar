@props(['dark' => false, 'fallback' => false])
{{-- How many times this page was viewed. Filled in by the analytics script (so CDN-cached pages stay exact) and only shown past a minimum. --}}
<span hidden {{ $fallback ? 'data-page-views-fallback' : 'data-page-views' }} {{ $attributes->class(['inline-flex items-center gap-1.5 tabular-nums', 'text-white/70' => $dark, 'text-muted-strong' => ! $dark]) }} title="{{ __('analytics.views_hint') }}">
    <x-icon name="eye" class="size-4 {{ $dark ? 'text-gold-300' : 'text-gold-600' }}" />
    <span><span data-page-views-count class="font-semibold {{ $dark ? 'text-white' : 'text-navy-900' }}"></span> {{ __('analytics.views_label') }}</span>
</span>
