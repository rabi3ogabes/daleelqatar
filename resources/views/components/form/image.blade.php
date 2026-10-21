@props(['name', 'label', 'current' => null, 'hint' => null, 'shape' => 'wide', 'removeAction' => null])
{{-- Image input with live preview. Images are re-encoded server-side (EXIF stripped). --}}
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $box = $shape === 'square' ? 'size-28' : 'aspect-[16/7] w-full';
@endphp
<div x-data="imagePicker(@js($current))" {{ $attributes->class('min-w-0') }}>
    <span class="label">{{ $label }}</span>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label for="{{ $id }}" class="group relative {{ $box }} flex cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-navy-200 bg-navy-50/50 transition hover:border-gold-400 hover:bg-gold-50/40">
            <template x-if="preview"><img :src="preview" alt="" class="absolute inset-0 size-full object-cover"></template>
            <span x-show="!preview" class="flex flex-col items-center gap-1 p-3 text-center text-xs text-muted-strong">
                <x-icon name="upload" class="size-6 text-gold-600" />{{ __('common.choose_file') }}
            </span>
            <span x-show="preview" x-cloak class="absolute inset-0 flex items-center justify-center bg-navy-950/50 text-xs font-semibold text-white opacity-0 transition group-hover:opacity-100">{{ __('common.replace') }}</span>
        </label>
        <div class="min-w-0 text-xs text-muted-strong">
            <p x-text="name" class="truncate font-medium text-navy-900"></p>
            <p>{{ $hint ?? __('common.image_requirements', ['size' => round((int) setting('uploads.max_image_kb', 5120) / 1024)]) }}</p>
            {{ $slot }}
        </div>
    </div>
    <input id="{{ $id }}" type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)">
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
