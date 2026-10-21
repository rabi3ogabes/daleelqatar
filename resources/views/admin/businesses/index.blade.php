<x-layouts.dashboard area="admin" :title="__('admin.businesses.title')" :subtitle="__('admin.businesses.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('admin.businesses.title')]]">
    <x-slot:actions>
        @can('businesses.export')
            <a href="{{ route('admin.businesses.export', request()->query()) }}" class="btn btn-outline"><x-icon name="download" class="size-4" />{{ __('admin.businesses.export') }}</a>
        @endcan
        @can('businesses.create')
            <a href="{{ route('admin.businesses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('admin.businesses.create') }}</a>
        @endcan
    </x-slot:actions>

    {{-- Status tabs --}}
    <nav class="mb-4 flex gap-1 overflow-x-auto scrollbar-none" aria-label="{{ __('common.status') }}">
        @php $current = request('status'); @endphp
        <a href="{{ route('admin.businesses.index', request()->except(['status', 'page'])) }}" @class(['btn btn-sm', 'btn-primary' => ! $current, 'btn-ghost' => $current])>{{ __('common.all') }} <span class="opacity-60">{{ format_number($statusCounts->sum()) }}</span></a>
        @foreach(\App\Enums\BusinessStatus::cases() as $s)
            <a href="{{ route('admin.businesses.index', array_merge(request()->except('page'), ['status' => $s->value])) }}" @class(['btn btn-sm', 'btn-primary' => $current === $s->value, 'btn-ghost' => $current !== $s->value])>
                {{ $s->label() }} <span class="opacity-60">{{ format_number($statusCounts[$s->value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <x-dash.filters :action="route('admin.businesses.index')">
        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="min-w-56 flex-1">
            <label class="label" for="flt-q">{{ __('common.search') }}</label>
            <input id="flt-q" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="{{ __('admin.businesses.search_placeholder') }}">
        </div>
        <div class="w-48">
            <label class="label" for="flt-cat">{{ __('search.category') }}</label>
            <select id="flt-cat" name="category" class="select">
                <option value="">{{ __('common.all') }}</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) request('category') === $c->id)>{{ $c->t('name') }}</option>@endforeach
            </select>
        </div>
        <div class="w-44">
            <label class="label" for="flt-mun">{{ __('search.municipality') }}</label>
            <select id="flt-mun" name="municipality" class="select">
                <option value="">{{ __('common.all') }}</option>
                @foreach($municipalities as $m)<option value="{{ $m->id }}" @selected((int) request('municipality') === $m->id)>{{ $m->t('name') }}</option>@endforeach
            </select>
        </div>
        @foreach(['verified' => __('business.verified'), 'featured' => __('business.featured'), 'demo' => __('common.sample_listing')] as $param => $label)
            <div class="w-32">
                <label class="label" for="flt-{{ $param }}">{{ $label }}</label>
                <select id="flt-{{ $param }}" name="{{ $param }}" class="select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="1" @selected(request($param) === '1')>{{ __('common.yes') }}</option>
                    <option value="0" @selected(request($param) === '0')>{{ __('common.no') }}</option>
                </select>
            </div>
        @endforeach
        <div class="w-40">
            <label class="label" for="flt-sort">{{ __('common.sort_by') }}</label>
            <select id="flt-sort" name="sort" class="select">
                @foreach(\App\Http\Controllers\Admin\BusinessController::SORTS as $s)<option value="{{ $s }}" @selected(request('sort', 'newest') === $s)>{{ __('admin.sort.'.$s) }}</option>@endforeach
            </select>
        </div>
        <div class="w-36">
            <label class="label" for="flt-trashed">{{ __('admin.businesses.deleted_filter') }}</label>
            <select id="flt-trashed" name="trashed" class="select">
                <option value="">{{ __('admin.businesses.active_only') }}</option>
                <option value="only" @selected(request('trashed') === 'only')>{{ __('admin.businesses.deleted_only') }}</option>
            </select>
        </div>
    </x-dash.filters>

    <x-dash.panel :padding="false">
        @if($businesses->isEmpty())
            <x-empty-state icon="store" :title="__('admin.businesses.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead>
                    <tr>
                        <th>{{ __('admin.businesses.business') }}</th>
                        <th>{{ __('search.category') }}</th>
                        <th>{{ __('admin.businesses.owner') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('admin.businesses.flags') }}</th>
                        <th>{{ __('common.updated_at') }}</th>
                        <th class="!text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businesses as $business)
                        <tr>
                            <td>
                                <a href="{{ route('admin.businesses.show', $business->id) }}" class="flex items-center gap-3">
                                    <x-business-cover :business="$business" class="size-11 shrink-0 rounded-lg" sizes="44px" />
                                    <span class="min-w-0">
                                        <span class="block max-w-64 truncate font-semibold text-navy-900 hover:text-gold-800">{{ $business->t('name') }}</span>
                                        <span class="block max-w-64 truncate text-xs text-muted-strong">{{ $business->locationLabel() ?? '—' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="text-muted-strong">{{ $business->category?->t('name') }}</td>
                            <td class="text-muted-strong">{{ $business->owner?->name ?? '—' }}</td>
                            <td>
                                @if($business->trashed())<span class="badge badge-danger">{{ __('admin.businesses.deleted_badge') }}</span>@else<x-status-badge :status="$business->status" />@endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if($business->is_verified)<span class="badge badge-verified"><x-icon name="badge-check" class="size-3" />{{ __('business.verified') }}</span>@endif
                                    @if($business->isFeaturedNow())<span class="badge badge-gold">{{ __('business.featured') }}</span>@endif
                                    @if($business->is_demo)<span class="badge badge-muted">{{ __('common.sample_listing') }}</span>@endif
                                </div>
                            </td>
                            <td class="text-xs whitespace-nowrap text-muted-strong">{{ format_date($business->updated_at) }}</td>
                            <td class="text-end whitespace-nowrap">
                                <a href="{{ route('admin.businesses.show', $business->id) }}" class="btn btn-ghost btn-sm">{{ __('common.details') }}</a>
                                @can('businesses.update')@unless($business->trashed())<a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('common.edit') }}"><x-icon name="pencil" class="size-4" /></a>@endunless @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $businesses->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
