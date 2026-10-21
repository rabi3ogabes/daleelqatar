@props(['name', 'stroke' => 1.75])
@php
    $paths = \App\Support\Icons::PATHS[$name] ?? \App\Support\Icons::PATHS['circle-help'];
    $flip = in_array($name, \App\Support\Icons::DIRECTIONAL, true);
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->class(['shrink-0', 'size-5' => ! str_contains((string) $attributes->get('class'), 'size-'), 'rtl:-scale-x-100' => $flip]) }}>{!! $paths !!}</svg>
