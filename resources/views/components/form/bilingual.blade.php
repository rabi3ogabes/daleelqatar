@props(['name', 'label', 'model' => null, 'type' => 'input', 'required' => false, 'rows' => 4, 'hint' => null, 'requiredEn' => null, 'maxlength' => null])
{{-- Renders paired Arabic (RTL) and English (LTR) fields: {name}_ar / {name}_en --}}
@php $requiredEn ??= $required; @endphp
<fieldset {{ $attributes->class('min-w-0') }}>
    <legend class="label">{{ $label }}@if($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</legend>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach(['ar' => [__('common.arabic'), 'rtl', $required], 'en' => [__('common.english'), 'ltr', $requiredEn]] as $lang => [$langLabel, $dir, $req])
            <div class="relative">
                <span class="pointer-events-none absolute -top-2 end-3 z-10 rounded bg-white px-1.5 text-[0.65rem] font-semibold tracking-wide text-gold-700">{{ $langLabel }}</span>
                @if($type === 'textarea')
                    <x-form.textarea :name="$name.'_'.$lang" :value="$model?->{$name.'_'.$lang}" :dir="$dir" :lang="$lang" :rows="$rows" :required="$req" :maxlength="$maxlength" aria-label="{{ $label }} — {{ $langLabel }}" />
                @else
                    <x-form.input :name="$name.'_'.$lang" :value="$model?->{$name.'_'.$lang}" :dir="$dir" :lang="$lang" :required="$req" :maxlength="$maxlength" aria-label="{{ $label }} — {{ $langLabel }}" />
                @endif
            </div>
        @endforeach
    </div>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
</fieldset>
