<x-layouts.dashboard area="admin" :title="__('discovery.title')" :subtitle="__('discovery.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('discovery.title')]]">
    <x-slot:actions>
        <a href="{{ route('admin.discovery.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('discovery.add') }}</a>
    </x-slot:actions>

    <x-flash class="mb-5" />

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        @foreach(['how_1' => 'radar', 'how_2' => 'shield-check', 'how_3' => 'layers'] as $key => $icon)
            <div class="card flex items-start gap-3 p-4 text-sm">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-gold-300"><x-icon :name="$icon" class="size-4.5" /></span>
                <p class="text-charcoal">{{ __('discovery.'.$key) }}</p>
            </div>
        @endforeach
    </div>

    @foreach($presets as $key => $preset)
        <div class="card mb-6 flex flex-col gap-4 overflow-hidden p-5 sm:flex-row sm:items-center">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-gold-400 to-gold-600 text-white shadow-sm"><x-icon name="sparkles" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-navy-900">{{ __('discovery.presets.'.$key.'.title') }}</p>
                <p class="mt-0.5 text-sm text-muted-strong">{{ __('discovery.presets.'.$key.'.text') }}</p>
            </div>
            <a href="{{ route('admin.discovery.create', ['preset' => $key]) }}" class="btn btn-gold shrink-0"><x-icon name="plus" class="size-4" />{{ __('discovery.presets.add') }}</a>
        </div>
    @endforeach

    <x-dash.panel :title="__('discovery.sources')" :subtitle="__('discovery.sources_hint')" :padding="false">
        @if($sources->isEmpty())
            <div class="p-5"><x-empty-state icon="radar" :title="__('discovery.empty')" :text="__('discovery.empty_text')" /></div>
        @else
            <x-dash.table>
                <thead>
                    <tr>
                        <th class="text-start">{{ __('discovery.source') }}</th>
                        <th class="text-start">{{ __('discovery.mode') }}</th>
                        <th class="text-start">{{ __('discovery.status_label') }}</th>
                        <th class="text-end">{{ __('discovery.queue') }}</th>
                        <th class="text-end">{{ __('discovery.created') }}</th>
                        <th class="text-end">{{ __('discovery.this_week') }}</th>
                        <th class="text-end">{{ __('discovery.merged') }}</th>
                        <th class="text-start">{{ __('discovery.last_run') }}</th>
                        <th class="text-end"><span class="sr-only">{{ __('discovery.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sources as $s)
                        @php
                            $c = $counts[$s->id] ?? [];
                            $stats = (array) $s->stats;
                            $ok = $s->last_status === null || $s->last_status === 'ok';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.discovery.edit', $s) }}" class="font-medium text-navy-900 hover:text-gold-700">{{ $s->name }}</a>
                                <span class="block max-w-xs truncate text-xs text-muted" dir="ltr">{{ $s->host() }}</span>
                            </td>
                            <td class="text-sm">{{ __('discovery.modes.'.$s->discovery) }}</td>
                            <td>
                                <span @class(['badge', 'badge-success' => $s->is_enabled && $ok, 'badge-danger' => $s->is_enabled && ! $ok, 'badge-muted' => ! $s->is_enabled])>
                                    {{ $s->is_enabled ? ($ok ? __('discovery.enabled') : __('discovery.attention')) : __('discovery.paused') }}
                                </span>
                                @unless($ok)<span class="mt-1 block max-w-[14rem] truncate text-xs text-danger" title="{{ $s->last_status }}">{{ $s->last_status }}</span>@endunless
                            </td>
                            <td class="text-end text-xs tabular-nums whitespace-nowrap">
                                <span class="font-semibold text-navy-900">{{ format_number($c['pending'] ?? 0) }}</span> <span class="text-muted">{{ __('discovery.pending') }}</span>
                                <span class="block text-muted-strong">{{ format_number($c['done'] ?? 0) }} {{ __('discovery.done') }} · {{ format_number(($c['failed'] ?? 0) + ($c['skipped'] ?? 0)) }} {{ __('discovery.skipped') }}</span>
                            </td>
                            <td class="text-end tabular-nums text-success">{{ format_number($stats['created'] ?? 0) }}</td>
                            <td class="text-end">
                                @if(($recent[$s->id] ?? 0) > 0)
                                    <a href="{{ route('admin.discovery.new', $s) }}" class="badge badge-success tabular-nums hover:opacity-80">+{{ format_number($recent[$s->id]) }}</a>
                                @else
                                    <a href="{{ route('admin.discovery.new', $s) }}" class="text-xs text-muted hover:text-navy-900">—</a>
                                @endif
                            </td>
                            <td class="text-end tabular-nums">{{ format_number($stats['merged'] ?? 0) }}</td>
                            <td class="whitespace-nowrap text-xs text-muted-strong">{{ $s->last_run_at?->diffForHumans() ?? __('discovery.never') }}</td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" class="btn btn-outline btn-sm" title="{{ __('discovery.run_hint') }}" data-no-busy
                                        @click="$dispatch('discovery-run', { id: {{ $s->id }} })"><x-icon name="play" class="size-3.5" />{{ __('discovery.run_now') }}</button>
                                    <a href="{{ route('admin.discovery.new', $s) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('discovery.new_title') }}" title="{{ __('discovery.new_title') }}"><x-icon name="sparkles" class="size-4" /></a>
                                    <a href="{{ route('admin.discovery.edit', $s) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('discovery.edit') }}"><x-icon name="pencil" class="size-4" /></a>
                                    <form method="POST" action="{{ route('admin.discovery.destroy', $s) }}" data-confirm="{{ __('discovery.confirm_delete') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('discovery.delete') }}"><x-icon name="trash-2" class="size-4" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
        @endif
    </x-dash.panel>

    @foreach($sources as $s)
        <div x-data="discoveryRunner(@js(['url' => route('admin.discovery.run', $s), 'pending' => (int) (($counts[$s->id] ?? [])['pending'] ?? 0)]))"
             @discovery-run.window="if ($event.detail.id === {{ $s->id }} && !busy) { open = true; start() }"
             x-show="open" x-cloak x-transition.opacity
             class="fixed inset-x-4 bottom-4 z-40 mx-auto max-w-xl sm:inset-x-auto sm:end-6 sm:bottom-6 sm:w-[28rem]" role="status" aria-live="polite">
            <div class="card overflow-hidden shadow-2xl ring-1 ring-navy-900/5">
                <div class="flex items-center gap-3 bg-navy-900 px-4 py-3 text-white">
                    <span class="flex size-8 items-center justify-center rounded-xl bg-white/10 text-gold-300">
                        <x-icon name="radar" class="size-4" x-show="!busy" /><x-icon name="loader-circle" class="size-4 animate-spin" x-show="busy" x-cloak />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $s->name }}</p>
                        <p class="text-xs text-white/60" x-text="busy ? @js(__('discovery.live.running')) : (error ? @js(__('discovery.live.stopped')) : @js(__('discovery.live.finished')))"></p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-white/60 hover:bg-white/10 hover:text-white" x-show="!busy" @click="open = false; if (created) location.reload()" aria-label="{{ __('common.close') }}"><x-icon name="x" class="size-4" /></button>
                </div>
                <div class="grid grid-cols-4 divide-x divide-navy-50 text-center rtl:divide-x-reverse">
                    @foreach(['found' => 'discovery.live.found', 'read' => 'discovery.live.read', 'created' => 'discovery.live.created', 'merged' => 'discovery.live.merged'] as $key => $label)
                        <div class="px-2 py-3">
                            <p class="text-lg font-semibold tabular-nums {{ $key === 'created' ? 'text-success' : 'text-navy-900' }}" x-text="{{ $key }}.toLocaleString()"></p>
                            <p class="text-[11px] leading-4 text-muted-strong">{{ __($label) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-3 border-t border-navy-50 px-4 py-3 text-xs">
                    <p class="flex items-center justify-between gap-2 text-muted-strong">
                        <span x-show="listing">{{ __('discovery.live.listing') }}</span>
                        <span x-show="!listing"><span class="font-semibold tabular-nums text-navy-900" x-text="pending.toLocaleString()"></span> {{ __('discovery.live.waiting') }}</span>
                        <span class="tabular-nums" x-show="runs">{{ __('discovery.live.rounds') }} <span x-text="runs"></span></span>
                    </p>
                    <p x-show="error" x-cloak class="rounded-lg bg-danger-soft px-3 py-2 text-danger" x-text="error"></p>
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.discovery.new', $s) }}" class="btn btn-ghost btn-sm" x-show="!busy && created > 0" x-cloak><x-icon name="sparkles" class="size-3.5" />{{ __('discovery.live.view_new') }}</a>
                        <button type="button" class="btn btn-outline btn-sm" x-show="busy" @click="stop()" :disabled="stopping" data-no-busy>
                            <x-icon name="square" class="size-3.5" /><span x-text="stopping ? @js(__('discovery.live.stopping')) : @js(__('discovery.live.stop'))"></span>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" x-show="!busy && (pending > 0 || listing)" x-cloak @click="start()" data-no-busy><x-icon name="play" class="size-3.5" />{{ __('discovery.live.continue') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4 text-sm text-gold-900">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-gold-600" />
        <div><p class="font-semibold">{{ __('discovery.legal_title') }}</p><p class="mt-1 leading-6">{{ __('discovery.legal_text') }}</p></div>
    </div>
</x-layouts.dashboard>
