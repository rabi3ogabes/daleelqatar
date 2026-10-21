@props(['name', 'multiple' => false, 'accept' => null, 'required' => false, 'id' => null])
{{-- Localized file picker: the native control is visually hidden, so the button text follows the page language. --}}
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
@endphp
<div x-data="{ names: '' }" {{ $attributes->class('min-w-0') }}>
    <div class="flex flex-wrap items-center gap-3">
        <label for="{{ $id }}" class="btn btn-outline btn-sm cursor-pointer focus-within:ring-4 focus-within:ring-gold-200">
            <x-icon name="upload" class="size-4" />{{ $multiple ? __('common.choose_files') : __('common.choose_file') }}
        </label>
        <span class="min-w-0 truncate text-xs text-muted-strong" x-text="names || @js(__('common.no_file_chosen'))">{{ __('common.no_file_chosen') }}</span>
    </div>
    <input id="{{ $id }}" type="file" name="{{ $name }}" class="sr-only" @if($multiple) multiple @endif @if($accept) accept="{{ $accept }}" @endif @if($required) required @endif
        @change="names = Array.from($event.target.files).map(f => f.name).join(document.dir === 'rtl' ? '، ' : ', ')">
    @error($key)<p class="error">{{ $message }}</p>@enderror
    @error($key.'.*')<p class="error">{{ $message }}</p>@enderror
</div>
