@props(['title', 'subtitle' => null, 'breadcrumbs' => [], 'icon' => null, 'eyebrow' => null])
<section class="relative overflow-hidden bg-navy-900">
    <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
    <div class="absolute -top-24 end-0 size-96 rounded-full bg-gold-400/10 blur-3xl" aria-hidden="true"></div>
    <div class="relative container-app py-10 sm:py-14">
        <x-breadcrumbs :items="$breadcrumbs" dark />
        <div class="mt-5 flex items-start gap-4">
            @if($icon)
                <span class="hidden size-14 shrink-0 items-center justify-center rounded-2xl border border-gold-400/30 bg-white/5 text-gold-300 sm:flex"><x-icon :name="$icon" class="size-7" :stroke="1.5" /></span>
            @endif
            <div class="min-w-0">
                @if($eyebrow)<p class="eyebrow mb-2 !text-gold-300">{{ $eyebrow }}</p>@endif
                <h1 class="text-2xl font-bold !text-white sm:text-4xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-2 max-w-2xl text-sm text-white/70 sm:text-base">{{ $subtitle }}</p>@endif
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
