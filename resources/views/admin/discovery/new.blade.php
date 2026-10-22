<x-layouts.dashboard area="admin" :title="__('discovery.new_title')" :subtitle="$source->name" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('discovery.title'), 'url' => route('admin.discovery.index')], ['label' => __('discovery.new_title')]]">
    <x-slot:actions>
        <a href="{{ route('admin.discovery.edit', $source) }}" class="btn btn-outline"><x-icon name="settings" class="size-4" />{{ __('discovery.edit') }}</a>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-dash.stat :label="__('discovery.new_stats.created', ['days' => $days])" :value="format_number($urls->total())" icon="sparkles" />
        <x-dash.stat :label="__('discovery.new_stats.completed', ['days' => $days])" :value="format_number($completed)" icon="layers" />
        <x-dash.stat :label="__('discovery.new_stats.waiting')" :value="format_number($waiting)" icon="clock" />
    </div>

    <nav class="mb-4 flex gap-1 overflow-x-auto scrollbar-none" aria-label="{{ __('discovery.new_period') }}">
        @foreach([1, 7, 30, 90] as $d)
            <a href="{{ route('admin.discovery.new', [$source, 'days' => $d]) }}" @class(['btn btn-sm', 'btn-primary' => $days === $d, 'btn-ghost' => $days !== $d])>{{ __('discovery.new_days.'.$d) }}</a>
        @endforeach
    </nav>

    <x-dash.panel :padding="false">
        @if($urls->isEmpty())
            <div class="p-5"><x-empty-state icon="radar" :title="__('discovery.new_empty')" :text="__('discovery.new_empty_text')" /></div>
        @else
            <x-dash.table>
                <thead>
                    <tr>
                        <th>{{ __('admin.businesses.business') }}</th>
                        <th>{{ __('search.category') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('discovery.new_found') }}</th>
                        <th class="!text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($urls as $url)
                        @php $business = $businesses[$url->business_id] ?? null; @endphp
                        <tr>
                            <td>
                                @if($business)
                                    <a href="{{ route('admin.businesses.show', $business->id) }}" class="flex items-center gap-3">
                                        <x-business-cover :business="$business" class="size-11 shrink-0 rounded-lg" sizes="44px" />
                                        <span class="min-w-0">
                                            <span class="block max-w-72 truncate font-semibold text-navy-900 hover:text-gold-800">{{ $business->t('name') }}</span>
                                            <span class="block max-w-72 truncate text-xs text-muted-strong">{{ $business->locationLabel() ?? '—' }}</span>
                                        </span>
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('discovery.new_removed') }}</span>
                                @endif
                            </td>
                            <td class="text-muted-strong">{{ $business?->category?->t('name') ?? '—' }}</td>
                            <td>
                                @if($business?->trashed())<span class="badge badge-danger">{{ __('admin.businesses.deleted_badge') }}</span>@elseif($business)<x-status-badge :status="$business->status" />@endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-muted-strong" title="{{ $url->fetched_at?->toDayDateTimeString() }}">{{ $url->fetched_at?->diffForHumans() }}</td>
                            <td class="whitespace-nowrap text-end">
                                <a href="{{ $url->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" title="{{ __('discovery.new_source_page') }}" aria-label="{{ __('discovery.new_source_page') }}"><x-icon name="external-link" class="size-4" /></a>
                                @if($business)<a href="{{ route('admin.businesses.show', $business->id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $urls->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
