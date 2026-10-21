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
                            <td class="text-end tabular-nums">{{ format_number($stats['merged'] ?? 0) }}</td>
                            <td class="whitespace-nowrap text-xs text-muted-strong">{{ $s->last_run_at?->diffForHumans() ?? __('discovery.never') }}</td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.discovery.run', $s) }}">
                                        @csrf
                                        <button class="btn btn-outline btn-sm" title="{{ __('discovery.run_hint') }}"><x-icon name="play" class="size-3.5" />{{ __('discovery.run_now') }}</button>
                                    </form>
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

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-gold-200 bg-gold-50/60 p-4 text-sm text-gold-900">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-gold-600" />
        <div><p class="font-semibold">{{ __('discovery.legal_title') }}</p><p class="mt-1 leading-6">{{ __('discovery.legal_text') }}</p></div>
    </div>
</x-layouts.dashboard>
