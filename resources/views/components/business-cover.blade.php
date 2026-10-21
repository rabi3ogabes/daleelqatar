@props(['business', 'class' => 'aspect-[4/3]', 'eager' => false, 'sizes' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw'])
@php $img = $business->cardImageUrl(); @endphp
<div {{ $attributes->class("relative overflow-hidden bg-navy-900 $class") }}>
    @if($img)
        <img src="{{ $img }}" alt="{{ $business->t('name') }}" sizes="{{ $sizes }}" @if(! $eager) loading="lazy" @else fetchpriority="high" @endif decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.04]">
    @else
        {{-- Branded placeholder: shown when the business has not uploaded photos (no stock imagery is invented). --}}
        <div class="pattern-lattice absolute inset-0 bg-gradient-to-br from-navy-800 via-navy-900 to-navy-950" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(215,184,117,0.22),transparent_55%)]" aria-hidden="true"></div>
        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center" aria-hidden="true">
            @if($business->logo_path)
                <img src="{{ $business->logoUrl() }}" alt="" loading="lazy" class="size-16 rounded-2xl bg-white object-contain p-1.5 shadow-lg">
            @else
                <span class="flex size-14 items-center justify-center rounded-2xl border border-gold-400/30 bg-white/5 text-gold-300 backdrop-blur">
                    <x-icon :name="$business->category?->icon ?? 'store'" class="size-7" :stroke="1.5" />
                </span>
            @endif
        </div>
    @endif
</div>
