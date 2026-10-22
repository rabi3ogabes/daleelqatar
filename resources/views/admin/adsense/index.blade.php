@php
    $score = $report['score'];
    $c = $report['counts'];
    $verdict = $c['fail'] === 0 ? ($c['warn'] === 0 ? 'ready' : 'almost') : ($c['fail'] <= 2 ? 'close' : 'work');
    $ring = 2 * M_PI * 52;
    $tone = ['pass' => ['circle-check', 'text-success', 'bg-success-soft'], 'warn' => ['triangle-alert', 'text-warning', 'bg-warning-soft'], 'fail' => ['circle-x', 'text-danger', 'bg-danger-soft'], 'info' => ['info', 'text-info', 'bg-navy-50']];
    $groupIcons = ['site' => 'globe', 'ads' => 'badge-dollar-sign', 'pages' => 'file-text', 'content' => 'store', 'experience' => 'smartphone'];
@endphp
<x-layouts.dashboard area="admin" :title="__('adsense.title')" :subtitle="__('adsense.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('adsense.title')]]">
    <x-slot:actions>
        <a href="{{ route('admin.adsense.index', ['refresh' => 1]) }}" class="btn btn-outline"><x-icon name="refresh-cw" class="size-4" />{{ __('adsense.recheck') }}</a>
        <a href="https://www.google.com/adsense/" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><x-icon name="external-link" class="size-4" />{{ __('adsense.open_adsense') }}</a>
    </x-slot:actions>

    {{-- Score --}}
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-navy-900 p-6 text-white shadow-lift sm:p-8">
        <div class="pattern-lattice absolute inset-0 opacity-40" aria-hidden="true"></div>
        <div class="absolute -top-24 end-0 size-80 rounded-full bg-gold-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-8">
            <div class="relative size-36 shrink-0" role="img" aria-label="{{ __('adsense.score_label', ['score' => $score]) }}">
                <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="10" />
                    <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $c['fail'] === 0 ? '#34D399' : '#E3B862' }}" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ round($ring * $score / 100, 1) }} {{ round($ring, 1) }}" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-4xl font-bold tabular-nums">{{ $score }}<span class="text-xl text-white/60">%</span></span>
                    <span class="text-[0.7rem] text-white/50">{{ __('adsense.ready_word') }}</span>
                </div>
            </div>
            <div class="min-w-0 flex-1 text-center sm:text-start">
                <p class="eyebrow !text-gold-300">{{ __('adsense.verdict_eyebrow') }}</p>
                <h2 class="mt-1 text-2xl font-bold !text-white sm:text-3xl">{{ __('adsense.verdict.'.$verdict.'.title') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/70">{{ __('adsense.verdict.'.$verdict.'.text') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2 sm:justify-start">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-semibold text-emerald-300"><x-icon name="circle-check" class="size-3.5" />{{ trans_choice('adsense.counts.pass', $c['pass'], ['count' => $c['pass']]) }}</span>
                    @if($c['warn'])<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 px-3 py-1 text-xs font-semibold text-amber-300"><x-icon name="triangle-alert" class="size-3.5" />{{ trans_choice('adsense.counts.warn', $c['warn'], ['count' => $c['warn']]) }}</span>@endif
                    @if($c['fail'])<span class="inline-flex items-center gap-1.5 rounded-full bg-rose-400/15 px-3 py-1 text-xs font-semibold text-rose-300"><x-icon name="circle-x" class="size-3.5" />{{ trans_choice('adsense.counts.fail', $c['fail'], ['count' => $c['fail']]) }}</span>@endif
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <div class="min-w-0 space-y-6">
            @foreach($report['groups'] as $group)
                @php $open = collect($group['items'])->whereIn('status', ['fail', 'warn'])->count(); @endphp
                <x-dash.panel :title="__('adsense.groups.'.$group['key'])" :padding="false">
                    <x-slot:actions>
                        @if($open)<span class="badge badge-warning">{{ trans_choice('adsense.to_fix', $open, ['count' => $open]) }}</span>@else<span class="badge badge-success"><x-icon name="check" class="size-3" />{{ __('adsense.group_done') }}</span>@endif
                    </x-slot:actions>
                    <ul class="divide-y divide-navy-50">
                        @foreach($group['items'] as $item)
                            @php [$icon, $text, $bg] = $tone[$item['status']]; $base = 'adsense.checks.'.$item['key']; @endphp
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $bg }} {{ $text }}"><x-icon :name="$icon" class="size-4.5" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-navy-900">{{ __($base.'.title') }}</p>
                                    <p class="mt-0.5 text-sm leading-6 text-muted-strong">{{ __($base.'.'.$item['status'], $item['params']) }}</p>
                                </div>
                                @if($item['action'])
                                    @php $external = ! str_starts_with($item['action']['url'], url('/')); @endphp
                                    <a href="{{ $item['action']['url'] }}" @if($external || in_array($item['action']['label'], ['open', 'view'], true)) target="_blank" rel="noopener noreferrer" @endif
                                        @class(['btn btn-sm shrink-0 self-start', 'btn-primary' => $item['status'] === 'fail', 'btn-outline' => $item['status'] !== 'fail'])>
                                        {{ __('adsense.actions.'.$item['action']['label']) }}<x-icon :name="$external ? 'external-link' : 'chevron-right'" class="size-3.5" />
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-dash.panel>
            @endforeach
        </div>

        <aside class="space-y-6">
            <x-dash.panel :title="__('adsense.console.title')">
                <div @class(['mb-4 flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium', 'bg-success-soft text-success' => $consoleCode || $consoleConfirmed, 'bg-warning-soft text-navy-900' => ! $consoleCode && ! $consoleConfirmed])>
                    <x-icon :name="$consoleCode || $consoleConfirmed ? 'circle-check' : 'triangle-alert'" class="size-4" />
                    {{ $consoleCode ? __('adsense.console.has_code') : ($consoleConfirmed ? __('adsense.console.confirmed') : __('adsense.console.missing')) }}
                </div>
                <p class="text-sm font-semibold text-navy-900">{{ __('adsense.console.find_title') }}</p>
                <ol class="mt-2 list-decimal space-y-1.5 ps-5 text-sm leading-6 text-muted-strong">
                    @foreach(__('adsense.console.steps') as $step)<li>{{ $step }}</li>@endforeach
                </ol>
                <div class="mt-4 rounded-xl border border-gold-200 bg-gold-50/60 p-3 text-sm leading-6 text-navy-900">
                    <p class="font-semibold">{{ __('adsense.console.domain_title') }}</p>
                    <p class="mt-1 text-muted-strong">{{ __('adsense.console.domain_text') }}</p>
                </div>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="https://search.google.com/search-console/settings" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm"><x-icon name="external-link" class="size-3.5" />{{ __('adsense.console.open') }}</a>
                    <a href="{{ route('admin.settings.edit', 'integrations') }}" class="btn btn-primary btn-sm"><x-icon name="settings" class="size-3.5" />{{ __('adsense.console.settings') }}</a>
                </div>
                <p class="mt-4 text-xs leading-5 text-muted">{{ __('adsense.console.sitemap', ['url' => url('/sitemap.xml')]) }}</p>
            </x-dash.panel>

            <x-dash.panel :title="__('adsense.apply.title')">
                <ol class="space-y-3 text-sm leading-6 text-muted-strong">
                    @foreach(__('adsense.apply.steps') as $i => $step)
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-semibold text-gold-300">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
                <p class="mt-4 rounded-xl bg-navy-50/70 p-3 text-xs leading-5 text-muted-strong">{{ __('adsense.apply.note') }}</p>
            </x-dash.panel>

            <p class="px-1 text-xs text-muted">{{ __('adsense.checked_at', ['time' => \Carbon\Carbon::parse($report['checked_at'])->diffForHumans()]) }}</p>
        </aside>
    </div>
</x-layouts.dashboard>
