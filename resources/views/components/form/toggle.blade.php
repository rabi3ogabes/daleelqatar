@props(['name', 'label', 'checked' => false, 'hint' => null, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $on = (bool) old($key, $checked);
@endphp
<div {{ $attributes->class('flex items-start justify-between gap-4') }}>
    <label for="{{ $id }}" class="min-w-0 cursor-pointer">
        <span class="block text-sm font-medium text-navy-900">{{ $label }}</span>
        @if($hint)<span class="mt-0.5 block text-xs text-muted-strong">{{ $hint }}</span>@endif
    </label>
    <span class="relative inline-flex shrink-0">
        <input type="hidden" name="{{ $name }}" value="0">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked($on) class="peer sr-only" role="switch">
        <span class="h-6 w-11 rounded-full bg-navy-200 transition peer-checked:bg-navy-900 peer-focus-visible:ring-4 peer-focus-visible:ring-gold-200" aria-hidden="true"></span>
        <span class="absolute top-0.5 start-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5 peer-checked:bg-gold-300" aria-hidden="true"></span>
    </span>
</div>
