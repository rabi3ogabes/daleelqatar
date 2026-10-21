@props(['title', 'subtitle' => null, 'nav' => [], 'area' => 'account', 'breadcrumbs' => []])
{{--
    Shared shell for the business-owner dashboard and the administration dashboard.
    $nav = [ ['heading' => '…'|null, 'items' => [ ['label', 'route', 'icon', 'pattern', 'badge' => int|null], … ]], … ]
--}}
@php
    $user = auth()->user();
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';
    seo()->title($title)->noindex();
    $roleLabel = $user?->getRoleNames()->map(fn ($r) => __('dashboard.roles.'.$r))->first() ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_is_rtl() ? 'rtl' : 'ltr' }}" data-confirm-title="{{ __('common.confirm_title') }}" data-confirm-label="{{ __('common.confirm_action') }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-[#F5F3EE]" x-data="{ side: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[90] focus:rounded-lg focus:bg-navy-900 focus:px-4 focus:py-2 focus:text-white">{{ __('nav.skip_to_content') }}</a>

    {{-- Sidebar --}}
    <div x-show="side" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-navy-950/60 backdrop-blur-sm lg:hidden" @click="side = false"></div>
    <aside class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col bg-navy-900 text-white transition-transform duration-300 lg:translate-x-0"
        :class="side ? 'translate-x-0' : 'ltr:-translate-x-full rtl:translate-x-full lg:!translate-x-0'" aria-label="{{ __('dashboard.sidebar') }}">
        <div class="pattern-lattice pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
        <div class="relative flex h-20 items-center justify-between px-6">
            <x-logo variant="dark" size="md" :href="route('home')" />
            <button type="button" class="btn-icon text-white/70 hover:bg-white/10 lg:hidden" @click="side = false"><x-icon name="x" /><span class="sr-only">{{ __('nav.close_menu') }}</span></button>
        </div>
        <nav class="relative flex-1 space-y-6 overflow-y-auto px-4 pt-2 pb-6 scrollbar-none">
            @foreach($nav as $section)
                <div>
                    @if(! empty($section['heading']))
                        <p class="mb-2 px-3 text-[0.68rem] font-semibold tracking-[0.14em] text-white/40 uppercase">{{ $section['heading'] }}</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach($section['items'] as $item)
                            @php $active = route_is(...(array) ($item['pattern'] ?? $item['route'])); @endphp
                            <li>
                                <a href="{{ route($item['route'], $item['params'] ?? []) }}" @if($active) aria-current="page" @endif
                                    @class([
                                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                        'bg-gold-400/15 text-gold-300 ring-1 ring-gold-400/20' => $active,
                                        'text-white/70 hover:bg-white/5 hover:text-white' => ! $active,
                                    ])>
                                    <x-icon :name="$item['icon']" class="size-[1.15rem] {{ $active ? 'text-gold-300' : 'text-white/45 group-hover:text-white/80' }}" />
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if(! empty($item['badge']))
                                        <span class="rounded-full bg-gold-400 px-1.5 py-0.5 text-[0.65rem] font-bold text-navy-950">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                    @else
                                        <x-icon name="chevron-right" class="size-3.5 opacity-0 transition group-hover:opacity-50 {{ $active ? '!opacity-60' : '' }}" />
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
        <div class="relative border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                @if($user?->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="" class="size-10 rounded-full object-cover ring-2 ring-gold-400/40">
                @else
                    <span class="flex size-10 items-center justify-center rounded-full bg-gold-400 text-sm font-bold text-navy-950">{{ $user?->initials() }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-white">{{ $user?->name }}</p>
                    <p class="truncate text-xs text-white/50">{{ $roleLabel }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn-icon size-9 text-white/60 hover:bg-white/10 hover:text-white" title="{{ __('nav.logout') }}"><x-icon name="log-out" class="size-4.5" /><span class="sr-only">{{ __('nav.logout') }}</span></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:ps-72">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-navy-100/70 bg-white/85 backdrop-blur-xl">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" class="btn-icon hover:bg-navy-50 lg:hidden" @click="side = true"><x-icon name="menu" /><span class="sr-only">{{ __('nav.menu') }}</span></button>
                <div class="min-w-0 flex-1">
                    @if($breadcrumbs)
                        <nav aria-label="{{ __('nav.breadcrumb') }}" class="hidden text-xs text-muted-strong sm:block">
                            <ol class="flex items-center gap-1.5">
                                @foreach($breadcrumbs as $crumb)
                                    <li class="flex items-center gap-1.5">
                                        @if(! $loop->first)<x-icon name="chevron-right" class="size-3 opacity-60" />@endif
                                        @if(! empty($crumb['url']) && ! $loop->last)<a href="{{ $crumb['url'] }}" class="hover:text-gold-700">{{ $crumb['label'] }}</a>@else<span class="text-navy-900">{{ $crumb['label'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif
                </div>
                <a href="{{ route('home') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex" target="_blank"><x-icon name="external-link" class="size-4" />{{ __('dashboard.view_site') }}</a>
                <a href="{{ localized_url($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" class="btn btn-ghost btn-sm"><x-icon name="languages" class="size-4 text-gold-600" />{{ __('nav.switch_language') }}</a>
                @php $unread = $user?->unreadNotifications()->count() ?? 0; @endphp
                <a href="{{ route($area === 'admin' ? 'admin.notifications' : 'account.notifications') }}" class="btn-icon relative hover:bg-navy-50" aria-label="{{ __('nav.notifications') }}">
                    <x-icon name="bell" />
                    @if($unread)<span class="absolute end-1.5 top-1.5 flex size-4 items-center justify-center rounded-full bg-gold-500 text-[0.6rem] font-bold text-white">{{ min($unread, 9) }}</span>@endif
                </a>
            </div>
        </header>

        <main id="main" tabindex="-1" class="px-4 py-6 outline-none sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-navy-900">{{ $title }}</h1>
                    @if($subtitle)<p class="mt-1 text-sm text-muted-strong">{{ $subtitle }}</p>@endif
                </div>
                @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
            </div>
            <x-flash />
            @if($errors->any() && ! session('error'))
                <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
                    <x-icon name="circle-alert" class="mt-0.5 size-5" /><p class="font-medium">{{ __('dashboard.fix_errors') }}</p>
                </div>
            @endif
            {{ $slot }}
        </main>
        @if($area === 'admin')
            @php $build = \App\Support\BuildInfo::get(); @endphp
            <footer class="flex flex-col gap-1 border-t border-navy-100 px-4 py-4 text-xs text-muted-strong sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <span>© {{ now()->year }} {{ __('brand.name') }}</span>
                <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1" dir="ltr">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="git-commit" class="size-3.5 text-gold-600" />{{ __('dashboard.version') }} <strong class="font-semibold text-navy-900">v{{ $build['version'] }}</strong>@if($build['short'])<code class="rounded bg-navy-50 px-1.5 py-0.5 text-[0.7rem] text-navy-700" title="{{ $build['commit'] }}">{{ $build['short'] }}</code>@endif</span>
                    @if($build['updated_at'])
                        <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="size-3.5 text-gold-600" />{{ __('dashboard.last_update') }} <time datetime="{{ $build['updated_at']->toIso8601String() }}" class="font-medium text-navy-900">{{ $build['updated_at']->format('Y-m-d H:i') }}</time> ({{ __('dashboard.qatar_time') }})</span>
                    @endif
                </span>
            </footer>
        @endif
    </div>
    @include('partials.ui')
    @stack('scripts')
</body>
</html>
