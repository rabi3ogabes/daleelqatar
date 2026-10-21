@props(['criteria' => null, 'categories', 'municipalities', 'variant' => 'hero'])
{{-- Main directory search: keyword + location + category, with live suggestions. --}}
@php $hero = $variant === 'hero'; @endphp
<form action="{{ route('search') }}" method="GET" role="search"
    class="relative flex flex-col gap-2 rounded-2xl bg-white p-2 shadow-lift ring-1 ring-navy-900/5 md:flex-row md:items-stretch md:gap-0 {{ $hero ? 'md:rounded-[1.25rem]' : '' }}">
    <div class="relative flex-1" x-data="searchBox(@js(route('search.suggest')), @js($criteria?->query ?? ''))" @click.outside="open = false">
        <label for="hero-q" class="sr-only">{{ __('home.search_placeholder') }}</label>
        <x-icon name="search" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-muted" />
        <input id="hero-q" name="q" type="search" x-model="q" @input="onInput" @focus="q.length > 1 && flat.length && (open = true)"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)" @keydown.escape="open = false"
            autocomplete="off" enterkeyhint="search" maxlength="120"
            role="combobox" aria-autocomplete="list" aria-controls="search-suggestions" :aria-expanded="open.toString()"
            placeholder="{{ __('home.search_placeholder') }}" value="{{ $criteria?->query }}"
            class="h-14 w-full rounded-xl border-0 bg-transparent ps-12 pe-4 text-[0.95rem] text-charcoal placeholder:text-muted focus:ring-2 focus:ring-gold-300 focus:outline-none">
        <span x-show="loading" x-cloak class="absolute end-4 top-1/2 -translate-y-1/2"><x-icon name="loader-circle" class="size-4 animate-spin text-muted" /></span>

        <div x-show="open" x-cloak x-transition.opacity id="search-suggestions" role="listbox"
            class="absolute inset-x-0 top-full z-40 mt-2 max-h-[22rem] overflow-y-auto rounded-2xl border border-navy-100 bg-white p-2 text-start shadow-lift">
            <template x-if="results.categories.length">
                <div>
                    <p class="px-3 pt-1 pb-1.5 text-[0.7rem] font-semibold tracking-wide text-muted uppercase">{{ __('search.suggest_categories') }}</p>
                    <template x-for="(item, i) in results.categories" :key="'c'+i">
                        <a :href="item.url" role="option" :aria-selected="active === i" :class="active === i ? 'bg-gold-50' : ''" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-navy-900 hover:bg-gold-50">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-navy-900 text-gold-300"><x-icon name="layout-grid" class="size-4" /></span>
                            <span x-text="item.name" class="font-medium"></span>
                        </a>
                    </template>
                </div>
            </template>
            <template x-if="results.businesses.length">
                <div>
                    <p class="px-3 pt-2 pb-1.5 text-[0.7rem] font-semibold tracking-wide text-muted uppercase">{{ __('search.suggest_businesses') }}</p>
                    <template x-for="(item, i) in results.businesses" :key="'b'+i">
                        <a :href="item.url" role="option" :aria-selected="active === i + results.categories.length" :class="active === i + results.categories.length ? 'bg-gold-50' : ''" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-gold-50">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-gold-50 text-gold-700"><x-icon name="store" class="size-4" /></span>
                            <span class="min-w-0">
                                <span class="flex items-center gap-1 text-sm font-medium text-navy-900"><span x-text="item.name" class="truncate"></span><template x-if="item.verified"><x-icon name="badge-check" class="size-4 text-gold-600" /></template></span>
                                <span x-text="item.meta" class="block truncate text-xs text-muted-strong"></span>
                            </span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div class="hidden w-px bg-navy-100 md:block" aria-hidden="true"></div>
    <div class="relative md:w-52">
        <label for="hero-loc" class="sr-only">{{ __('search.municipality') }}</label>
        <x-icon name="map-pin" class="pointer-events-none absolute start-4 top-1/2 size-4.5 -translate-y-1/2 text-gold-600" />
        <select id="hero-loc" name="municipality" class="h-14 w-full appearance-none rounded-xl border-0 bg-transparent ps-11 pe-8 text-sm font-medium text-navy-900 focus:ring-2 focus:ring-gold-300 focus:outline-none">
            <option value="">{{ __('home.location_placeholder') }}</option>
            @foreach($municipalities as $m)
                <option value="{{ $m->slug }}" @selected($criteria?->municipality?->id === $m->id)>{{ app()->getLocale() === 'ar' ? 'قطر - '.$m->t('name') : $m->t('name').', Qatar' }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
    </div>

    <div class="hidden w-px bg-navy-100 md:block" aria-hidden="true"></div>
    <div class="relative md:w-48">
        <label for="hero-cat" class="sr-only">{{ __('search.category') }}</label>
        <x-icon name="layout-grid" class="pointer-events-none absolute start-4 top-1/2 size-4.5 -translate-y-1/2 text-gold-600" />
        <select id="hero-cat" name="category" class="h-14 w-full appearance-none rounded-xl border-0 bg-transparent ps-11 pe-8 text-sm font-medium text-navy-900 focus:ring-2 focus:ring-gold-300 focus:outline-none">
            <option value="">{{ __('home.category_placeholder') }}</option>
            @foreach($categories as $c)
                <option value="{{ $c->slug }}" @selected($criteria?->category?->id === $c->id)>{{ $c->t('name') }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
    </div>

    <button type="submit" data-no-busy class="btn btn-gold h-14 rounded-xl px-7 text-base md:ms-2">
        <x-icon name="search" class="size-5" /><span>{{ __('home.search_button') }}</span>
    </button>
</form>
