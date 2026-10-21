@php
    $user = auth()->user();
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $links = [
        ['home', __('nav.home'), 'home'],
        ['categories.index', __('nav.categories'), 'categories.*'],
        ['search', __('nav.explore'), 'search'],
        ['locations.index', __('nav.locations'), 'locations.*'],
        ['offers.index', __('nav.offers'), 'offers.*'],
        ['about', __('nav.about'), 'about'],
        ['contact', __('nav.contact'), 'contact'],
    ];
@endphp
<header x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 8"
    class="sticky top-0 z-50 border-b bg-white/90 backdrop-blur-xl transition" :class="scrolled ? 'border-navy-100 shadow-soft' : 'border-transparent'">
    <div class="container-app flex h-[4.5rem] items-center gap-6">
        <x-logo :href="route('home')" size="md" />

        <nav aria-label="{{ __('nav.main') }}" class="hidden flex-1 items-center justify-center gap-1 lg:flex">
            @foreach($links as [$route, $label, $pattern])
                <a href="{{ route($route) }}" @class([
                    'relative rounded-lg px-3 py-2 text-sm font-medium transition',
                    'text-navy-900 after:absolute after:inset-x-3 after:-bottom-[0.95rem] after:h-0.5 after:rounded-full after:bg-gold-500' => route_is($pattern),
                    'text-muted-strong hover:text-navy-900' => ! route_is($pattern),
                ]) @if(route_is($pattern)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ms-auto flex items-center gap-2 lg:ms-0">
            <a href="{{ localized_url($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" class="hidden items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-navy-800 transition hover:bg-navy-50 sm:inline-flex" aria-label="{{ __('nav.switch_language_label') }}">
                <x-icon name="languages" class="size-4.5 text-gold-600" />{{ __('nav.switch_language') }}
            </a>

            @guest
                <a href="{{ route('login') }}" class="btn btn-ghost hidden md:inline-flex">{{ __('nav.login') }}</a>
                <a href="{{ route('add-business') }}" class="btn btn-primary hidden md:inline-flex"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
            @else
                <a href="{{ route('account.businesses.create') }}" class="btn btn-primary hidden xl:inline-flex"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
                <div x-data="{ menu: false }" class="relative" @keydown.escape="menu = false" @click.outside="menu = false">
                    <button type="button" @click="menu = !menu" :aria-expanded="menu.toString()" aria-haspopup="menu" class="flex items-center gap-2 rounded-full p-0.5 pe-2 ring-1 ring-navy-100 transition hover:ring-gold-300">
                        @if($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="" class="size-8 rounded-full object-cover">
                        @else
                            <span class="flex size-8 items-center justify-center rounded-full bg-navy-900 text-xs font-semibold text-gold-300">{{ $user->initials() }}</span>
                        @endif
                        <x-icon name="chevron-down" class="size-4 text-muted" />
                        <span class="sr-only">{{ __('nav.account') }}</span>
                    </button>
                    <div x-show="menu" x-cloak x-transition.origin.top role="menu" class="absolute end-0 mt-2 w-64 overflow-hidden rounded-2xl border border-navy-100 bg-white p-1.5 shadow-lift">
                        <div class="px-3 py-2.5">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-muted-strong">{{ $user->email }}</p>
                        </div>
                        <div class="my-1 h-px bg-navy-50"></div>
                        @if($user->can('admin.access'))
                            <a role="menuitem" href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="shield-check" class="size-4.5 text-gold-600" />{{ __('nav.admin') }}</a>
                        @endif
                        <a role="menuitem" href="{{ route('account.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="layout-dashboard" class="size-4.5 text-muted" />{{ __('nav.dashboard') }}</a>
                        <a role="menuitem" href="{{ route('account.businesses.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="store" class="size-4.5 text-muted" />{{ __('nav.my_businesses') }}</a>
                        <a role="menuitem" href="{{ route('account.favorites') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="heart" class="size-4.5 text-muted" />{{ __('nav.favorites') }}</a>
                        <a role="menuitem" href="{{ route('account.notifications') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="bell" class="size-4.5 text-muted" />{{ __('nav.notifications') }}
                            @if(($unread = $user->unreadNotifications()->count()) > 0)<span class="ms-auto rounded-full bg-gold-400 px-1.5 text-[0.65rem] font-bold text-navy-950">{{ $unread }}</span>@endif
                        </a>
                        <a role="menuitem" href="{{ route('account.settings') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-navy-800 hover:bg-gold-50"><x-icon name="settings" class="size-4.5 text-muted" />{{ __('nav.settings') }}</a>
                        <div class="my-1 h-px bg-navy-50"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-danger hover:bg-danger-soft"><x-icon name="log-out" class="size-4.5" />{{ __('nav.logout') }}</button>
                        </form>
                    </div>
                </div>
            @endguest

            <button type="button" class="btn-icon text-navy-900 hover:bg-navy-50 lg:hidden" @click="open = true" :aria-expanded="open.toString()" aria-controls="mobile-nav">
                <x-icon name="menu" class="size-6" /><span class="sr-only">{{ __('nav.menu') }}</span>
            </button>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true" id="mobile-nav">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-950/60 backdrop-blur-sm" @click="open = false"></div>
        <div x-show="open" x-trap.noscroll="open" @keydown.escape="open = false"
            x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="ltr:translate-x-full rtl:-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="ltr:translate-x-full rtl:-translate-x-full"
            class="absolute inset-y-0 end-0 flex w-full max-w-sm flex-col bg-white shadow-lift">
            <div class="flex h-[4.5rem] items-center justify-between border-b border-navy-50 px-5">
                <x-logo size="sm" />
                <button type="button" class="btn-icon hover:bg-navy-50" @click="open = false"><x-icon name="x" class="size-6" /><span class="sr-only">{{ __('nav.close_menu') }}</span></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('nav.main') }}">
                @foreach($links as [$route, $label, $pattern])
                    <a href="{{ route($route) }}" @class(['flex items-center justify-between rounded-xl px-4 py-3 text-base font-medium', 'bg-gold-50 text-navy-900' => route_is($pattern), 'text-navy-800 hover:bg-navy-50' => ! route_is($pattern)])>
                        {{ $label }}<x-icon name="chevron-right" class="size-4 text-muted" />
                    </a>
                @endforeach
                <a href="{{ localized_url($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" class="mt-2 flex items-center gap-2 rounded-xl px-4 py-3 text-base font-semibold text-navy-900 hover:bg-navy-50">
                    <x-icon name="languages" class="size-5 text-gold-600" />{{ __('nav.switch_language') }}
                </a>
            </nav>
            <div class="space-y-2 border-t border-navy-50 p-5">
                @guest
                    <a href="{{ route('add-business') }}" class="btn btn-gold w-full"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('login') }}" class="btn btn-outline">{{ __('nav.login') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">{{ __('nav.register') }}</a>
                    </div>
                @else
                    <a href="{{ route('account.dashboard') }}" class="btn btn-primary w-full"><x-icon name="layout-dashboard" class="size-4" />{{ __('nav.dashboard') }}</a>
                    @can('admin.access')<a href="{{ route('admin.dashboard') }}" class="btn btn-outline w-full"><x-icon name="shield-check" class="size-4" />{{ __('nav.admin') }}</a>@endcan
                @endguest
            </div>
        </div>
    </div>
</header>
