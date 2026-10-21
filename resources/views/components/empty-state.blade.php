@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->class('flex flex-col items-center justify-center rounded-2xl border border-dashed border-navy-200 bg-white/60 px-6 py-14 text-center') }}>
    <span class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-gold-50 text-gold-700 ring-1 ring-gold-100">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="text-base font-semibold text-navy-900">{{ $title }}</h3>
    @if($text)<p class="mt-1.5 max-w-md text-sm text-muted-strong">{{ $text }}</p>@endif
    @if(trim($slot))<div class="mt-5 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>@endif
</div>
