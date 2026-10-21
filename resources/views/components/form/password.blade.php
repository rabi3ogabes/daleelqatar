@props(['name' => 'password', 'label', 'autocomplete' => 'current-password', 'hint' => null, 'required' => true])
@php $id = 'f_'.$name; $hasError = $errors->has($name); @endphp
<div {{ $attributes->class('min-w-0') }} x-data="{ show: false }">
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <div class="relative">
        <x-icon name="lock" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
        <input id="{{ $id }}" name="{{ $name }}" :type="show ? 'text' : 'password'" type="password" autocomplete="{{ $autocomplete }}" dir="ltr"
            @if($required) required aria-required="true" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @elseif($hint) aria-describedby="{{ $id }}_hint" @endif
            @class(['input ps-10 pe-11', 'input-invalid' => $hasError])>
        <button type="button" @click="show = !show" class="absolute end-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted hover:bg-navy-50 hover:text-navy-900"
            :aria-label="show ? @js(__('auth_ui.hide_password')) : @js(__('auth_ui.show_password'))" :aria-pressed="show.toString()">
            <x-icon name="eye" class="size-4.5" x-show="!show" /><x-icon name="eye-off" class="size-4.5" x-show="show" x-cloak />
        </button>
    </div>
    @if($hasError)
        <p id="{{ $id }}_error" class="error">{{ $errors->first($name) }}</p>
    @elseif($hint)
        <p id="{{ $id }}_hint" class="hint">{{ $hint }}</p>
    @endif
</div>
