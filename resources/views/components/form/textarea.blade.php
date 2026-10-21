@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4, 'dir' => null, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($key);
@endphp
<div {{ $attributes->only('class')->class('min-w-0') }}>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if($dir) dir="{{ $dir }}" @endif
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @elseif($hint) aria-describedby="{{ $id }}_hint" @endif
        {{ $attributes->except('class')->class(['textarea', 'input-invalid' => $hasError]) }}>{{ old($key, $value) }}</textarea>
    @if($hasError)
        <p id="{{ $id }}_error" class="error">{{ $errors->first($key) }}</p>
    @elseif($hint)
        <p id="{{ $id }}_hint" class="hint">{{ $hint }}</p>
    @endif
</div>
