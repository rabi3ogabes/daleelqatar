<x-layouts.dashboard area="admin" :title="__('guides.admin.title')" :subtitle="__('guides.admin.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('guides.admin.title')]]">
    <x-slot:actions>
        <a href="{{ route('admin.guides.suggestions') }}" class="btn btn-outline"><x-icon name="sparkles" class="size-4" />{{ __('guides.admin.ideas') }}</a>
        <a href="{{ route('admin.guides.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('guides.admin.create') }}</a>
    </x-slot:actions>

    <x-dash.panel :padding="false">
        @if($guides->isEmpty())
            <x-empty-state icon="book-marked" :title="__('guides.admin.empty_title')" :text="__('guides.admin.empty_text')" class="m-5">
                <a href="{{ route('admin.guides.suggestions') }}" class="btn btn-gold"><x-icon name="sparkles" class="size-4" />{{ __('guides.admin.ideas') }}</a>
            </x-empty-state>
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('guides.admin.guide') }}</th><th>{{ __('guides.admin.listings') }}</th><th>{{ __('common.status') }}</th><th>{{ __('guides.admin.updated') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($guides as $guide)
                        @php $n = $counts[$guide->id] ?? 0; @endphp
                        <tr>
                            <td class="max-w-sm"><span class="block truncate font-semibold text-navy-900">{{ $guide->t('title') }}</span><span class="block text-xs text-muted-strong">{{ collect([$guide->category?->t('name'), $guide->area?->t('name') ?? $guide->municipality?->t('name'), $guide->open_24h ? '24/7' : null])->filter()->implode(' · ') }}</span></td>
                            <td><span @class(['tabular-nums', 'text-danger font-semibold' => $n < \App\Support\Guides\GuideBuilder::MIN_LISTINGS])>{{ format_number(min($n, $guide->max_items)) }}</span></td>
                            <td>@if($guide->isPublished())<span class="badge badge-success">{{ __('guides.admin.published') }}</span>@else<span class="badge badge-muted">{{ __('guides.admin.draft') }}</span>@endif</td>
                            <td class="text-xs text-muted-strong">{{ format_date($guide->updated_at) }}</td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('guides.show', $guide) }}" target="_blank" class="btn btn-ghost btn-sm" aria-label="{{ __('guides.admin.view') }}"><x-icon name="external-link" class="size-4" /></a>
                                    <a href="{{ route('admin.guides.edit', $guide) }}" class="btn btn-ghost btn-sm">{{ __('common.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.guides.destroy', $guide) }}" data-confirm="{{ __('guides.admin.delete_confirm') }}">@csrf @method('DELETE')
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
