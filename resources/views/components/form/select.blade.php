@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false, 'id' => null])
{{-- $options: [value => label] or [groupLabel => [value => label]] --}}
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($key);
    $current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class')->class('min-w-0') }}>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif
        {{ $attributes->except('class')->class(['select', 'input-invalid' => $hasError]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optValue => $optLabel)
            @if(is_array($optLabel))
                <optgroup label="{{ $optValue }}">
                    @foreach($optLabel as $v => $l)
                        <option value="{{ $v }}" @selected($current === (string) $v)>{{ $l }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
            @endif
        @endforeach
        {{ $slot }}
    </select>
    @if($hasError)
        <p id="{{ $id }}_error" class="error">{{ $errors->first($key) }}</p>
    @elseif($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
</div>
