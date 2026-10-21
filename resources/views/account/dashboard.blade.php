<x-layouts.account :title="__('account.dashboard.title')">

    {{-- Welcome --}}
    <section class="relative mb-6 overflow-hidden rounded-2xl bg-navy-900 p-6 text-white sm:p-8">
        <div class="pattern-lattice absolute inset-0" aria-hidden="true"></div>
        <div class="absolute -end-16 -top-20 size-64 rounded-full bg-gold-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-2xl font-bold">{{ __('account.dashboard.welcome', ['name' => \Illuminate\Support\Str::before($user->name, ' ')]) }}</p>
                <p class="mt-1 text-sm text-white/70">{{ $businesses->isEmpty() ? __('account.dashboard.member_hint') : __('account.dashboard.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('search') }}" class="btn btn-outline-light"><x-icon name="compass" class="size-4" />{{ __('account.dashboard.explore') }}</a>
                <a href="{{ route('account.businesses.create') }}" class="btn btn-gold"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
            </div>
        </div>
    </section>

    {{-- Stats (live data) --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-dash.stat :label="__('account.dashboard.stat_listings')" :value="$stats['listings']" icon="store" :sublabel="trans_choice('account.dashboard.stat_published', $stats['published'], ['count' => $stats['published']])" :href="route('account.businesses.index')" />
        <x-dash.stat :label="__('account.dashboard.stat_views')" :value="$stats['views']" icon="eye" :sublabel="__('account.dashboard.last_30')" />
        <x-dash.stat :label="__('account.dashboard.stat_interactions')" :value="$stats['interactions']" icon="mouse-pointer-click" :sublabel="__('account.dashboard.last_30')" />
        <x-dash.stat :label="__('account.dashboard.stat_reviews')" :value="$stats['reviews']" icon="star" :href="route('account.reviews.index')" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-dash.panel :title="__('account.dashboard.listings')" :padding="false">
                <x-slot:actions><a href="{{ route('account.businesses.index') }}" class="text-sm font-semibold text-gold-700 hover:text-gold-800">{{ __('common.view_all') }}</a></x-slot:actions>
                @if($businesses->isEmpty())
                    <div class="p-5">
                        <x-empty-state icon="store" :title="__('account.dashboard.no_listings_title')" :text="__('account.dashboard.no_listings_text')">
                            <a href="{{ route('account.businesses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
                        </x-empty-state>
                    </div>
                @else
                    <ul class="divide-y divide-navy-50">
                        @foreach($businesses->take(5) as $business)
                            @include('account.partials.listing-row', ['business' => $business, 'missing' => $missing[$business->id] ?? []])
                        @endforeach
                    </ul>
                @endif
            </x-dash.panel>

            @if($businesses->isNotEmpty())
                <x-dash.panel :title="__('account.dashboard.views_chart')" :subtitle="__('account.dashboard.last_30')">
                    @if(array_sum($chartValues) > 0)
                        <x-chart.line :labels="$chartLabels" :values="$chartValues" :title="__('account.dashboard.views_chart')" />
                    @else
                        <p class="py-10 text-center text-sm text-muted-strong">{{ __('dashboard.no_data') }}</p>
                    @endif
                </x-dash.panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-dash.panel :title="__('account.dashboard.quick_actions')">
                <div class="grid grid-cols-2 gap-2">
                    @foreach([
                        ['account.businesses.create', 'plus', __('account.nav.add_business')],
                        ['account.offers.create', 'tag', __('account.offers.create')],
                        ['account.reviews.index', 'star', __('account.nav.reviews')],
                        ['account.favorites', 'heart', __('account.nav.favorites')],
                        ['account.settings', 'settings', __('account.nav.settings')],
                        ['search', 'search', __('nav.explore')],
                    ] as [$route, $icon, $label])
                        <a href="{{ route($route) }}" class="flex flex-col items-center gap-2 rounded-xl border border-navy-100 p-3 text-center text-xs font-medium text-navy-800 transition hover:border-gold-300 hover:bg-gold-50">
                            <x-icon :name="$icon" class="size-5 text-gold-600" />{{ $label }}
                        </a>
                    @endforeach
                </div>
            </x-dash.panel>

            <x-dash.panel :title="__('account.dashboard.recent_notifications')" :padding="false">
                <x-slot:actions><a href="{{ route('account.notifications') }}" class="text-sm font-semibold text-gold-700 hover:text-gold-800">{{ __('common.view_all') }}</a></x-slot:actions>
                @if($notifications->isEmpty())
                    <p class="p-5 text-sm text-muted-strong">{{ __('notifications.empty') }}</p>
                @else
                    <ul class="space-y-1 p-2">
                        @foreach($notifications as $notification)
                            @include('account.partials.notification-item')
                        @endforeach
                    </ul>
                @endif
            </x-dash.panel>
        </div>
    </div>
</x-layouts.account>
