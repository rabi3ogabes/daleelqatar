<x-layouts.account :title="__('account.businesses.title')" :subtitle="__('account.businesses.subtitle')" :breadcrumbs="[['label' => __('account.businesses.title')]]">
    <x-slot:actions>
        <a href="{{ route('account.businesses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
    </x-slot:actions>

    @if($businesses->isEmpty())
        <x-empty-state icon="store" :title="__('account.businesses.empty_title')" :text="__('account.businesses.empty_text')">
            <a href="{{ route('account.businesses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" />{{ __('nav.add_business') }}</a>
            <a href="{{ route('search') }}" class="btn btn-outline">{{ __('pages.add_already_listed') }}</a>
        </x-empty-state>
    @else
        <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
            @foreach($businesses as $business)
                @php $pct = $completeness[$business->id] ?? 0; @endphp
                <article class="card group flex flex-col overflow-hidden">
                    <div class="relative">
                        <x-business-cover :business="$business" class="aspect-[16/8]" />
                        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
                            <x-status-badge :status="$business->status" class="bg-white/95 backdrop-blur" />
                            @if($business->is_verified)<span class="badge badge-verified"><x-icon name="badge-check" class="size-3.5" />{{ __('business.verified') }}</span>@endif
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <h2 class="font-semibold text-navy-900"><a href="{{ route('account.businesses.show', $business->uuid) }}" class="hover:text-gold-800">{{ $business->t('name') }}</a></h2>
                        <p class="mt-0.5 text-xs text-muted-strong">{{ $business->category?->t('name') }}@if($business->locationLabel()) · {{ $business->locationLabel() }}@endif</p>
                        <p class="mt-2 text-xs font-medium text-gold-700">{{ __('account.next.'.$business->status->value) }}</p>

                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs"><span class="text-muted-strong">{{ __('account.businesses.completeness') }}</span><span class="font-semibold text-navy-900 tabular-nums">{{ $pct }}%</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-navy-50" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('account.businesses.completeness') }}">
                                <div class="h-full rounded-full {{ $pct >= 80 ? 'bg-success' : 'bg-gold-500' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between gap-2 border-t border-navy-50 pt-4 text-xs text-muted">
                            <span class="inline-flex items-center gap-1"><x-icon name="eye" class="size-3.5" />{{ format_number($business->views_count) }}</span>
                            <span>{{ __('account.businesses.last_updated', ['date' => format_date($business->updated_at)]) }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('account.businesses.show', $business->uuid) }}" class="btn btn-outline btn-sm flex-1">{{ __('account.businesses.open_listing') }}</a>
                            @can('update', $business)
                                <a href="{{ route('account.businesses.edit', $business->uuid) }}" class="btn btn-primary btn-sm flex-1"><x-icon name="pencil" class="size-3.5" />{{ __('common.edit') }}</a>
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $businesses->links() }}
    @endif
</x-layouts.account>
