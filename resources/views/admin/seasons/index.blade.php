<x-layouts.dashboard area="admin" :title="__('seasons.admin.title')" :subtitle="__('seasons.admin.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('seasons.admin.title')]]">
    <x-slot:actions>
        <a href="{{ route('admin.seasons.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('seasons.admin.create') }}</a>
    </x-slot:actions>

    <p class="mb-5 flex items-start gap-2 rounded-2xl bg-info-soft p-4 text-sm text-info"><x-icon name="info" class="mt-0.5 size-4 shrink-0" />{{ __('seasons.admin.help') }}</p>

    <x-dash.panel :padding="false">
        @if($seasons->isEmpty())
            <x-empty-state icon="moon" :title="__('seasons.admin.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('seasons.admin.name') }}</th><th>{{ __('seasons.admin.dates') }}</th><th>{{ __('seasons.admin.businesses') }}</th><th>{{ __('common.status') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($seasons as $season)
                        <tr>
                            <td><span class="font-semibold text-navy-900">{{ $season->t('name') }}</span><span class="block text-xs text-muted-strong">{{ __('seasons.kind.'.$season->kind) }}</span></td>
                            <td class="text-sm">{{ $season->dateRange() }}</td>
                            <td class="tabular-nums">{{ format_number($counts[$season->id] ?? 0) }}</td>
                            <td>
                                @if(! $season->is_active)<span class="badge badge-muted">{{ __('seasons.admin.inactive') }}</span>
                                @elseif($season->isRunning())<span class="badge badge-success">{{ __('seasons.running_now') }}</span>
                                @elseif($season->ends_on->isPast())<span class="badge badge-muted">{{ __('seasons.admin.ended') }}</span>
                                @else<span class="badge badge-info">{{ __('seasons.admin.scheduled') }}</span>@endif
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.seasons.edit', $season) }}" class="btn btn-ghost btn-sm">{{ __('common.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.seasons.destroy', $season) }}" data-confirm="{{ __('seasons.admin.delete_confirm') }}">@csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-danger" aria-label="{{ __('common.delete') }}"><x-icon name="trash-2" class="size-4" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
