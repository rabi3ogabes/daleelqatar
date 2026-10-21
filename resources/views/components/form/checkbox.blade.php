@props(['name', 'label', 'checked' => false, 'value' => '1', 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name).'_'.$value;
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class')->class('min-w-0') }}>
    <label for="{{ $id }}" class="flex cursor-pointer items-start gap-2.5 text-sm text-charcoal">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($key, $checked)) {{ $attributes->except('class')->class('checkbox mt-0.5') }}>
        <span>{{ $label }}</span>
    </label>
    @error($key)<p class="error">{{ $message }}</p>@enderror
</div>
