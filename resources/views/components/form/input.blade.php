@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'dir' => null, 'icon' => null, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($key);
    $val = $type === 'password' ? null : old($key, $value);
@endphp
<div {{ $attributes->only('class')->class('min-w-0') }}>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    {{-- The wrapper shares the input's direction so the icon and its padding stay on the same side. --}}
    <div class="relative" @if($dir) dir="{{ $dir }}" @endif>
        @if($icon)<x-icon :name="$icon" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if($val !== null) value="{{ $val }}" @endif
            @if($dir) dir="{{ $dir }}" @endif
            @if($required) required aria-required="true" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @elseif($hint) aria-describedby="{{ $id }}_hint" @endif
            {{ $attributes->except('class')->class(['input', 'input-invalid' => $hasError, 'ps-10' => $icon]) }}>
    </div>
    @if($hasError)
        <p id="{{ $id }}_error" class="error">{{ $errors->first($key) }}</p>
    @elseif($hint)
        <p id="{{ $id }}_hint" class="hint">{{ $hint }}</p>
    @endif
</div>
