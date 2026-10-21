<x-layouts.account :title="__('account.claims.title')" :subtitle="__('account.claims.subtitle')" :breadcrumbs="[['label' => __('account.claims.title')]]">
    @if($claims->isEmpty())
        <x-empty-state icon="flag" :title="__('account.claims.empty_title')" :text="__('account.claims.empty_text')">
            <a href="{{ route('search') }}" class="btn btn-primary"><x-icon name="search" class="size-4" />{{ __('search.title') }}</a>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <ul class="divide-y divide-navy-50">
                @foreach($claims as $claim)
                    <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-navy-900">
                                @if($claim->business?->isPublished())<a href="{{ route('business.show', $claim->business) }}" class="hover:text-gold-800">{{ $claim->business->t('name') }}</a>@else{{ $claim->business?->t('name') }}@endif
                            </p>
                            <p class="mt-0.5 text-xs text-muted">{{ __('account.claims.submitted_on', ['date' => format_date($claim->created_at)]) }}</p>
                            @if($claim->decision_note)
                                <p class="mt-2 text-sm text-muted-strong"><span class="font-medium text-navy-900">{{ __('account.claims.decision') }}:</span> {{ $claim->decision_note }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <x-status-badge :status="$claim->status" />
                            @if($claim->status === \App\Enums\RequestStatus::Approved && $claim->business && $claim->business->owner_id === auth()->id())
                                <a href="{{ route('account.businesses.show', $claim->business->uuid) }}" class="btn btn-primary btn-sm">{{ __('account.businesses.open_listing') }}</a>
                            @endif
                            @if($claim->status === \App\Enums\RequestStatus::Pending)
                                <form method="POST" action="{{ route('account.claims.cancel', $claim->uuid) }}" data-confirm="{{ __('account.claims.cancel_confirm') }}">@csrf
                                    <button class="btn btn-danger-outline btn-sm">{{ __('account.claims.cancel') }}</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        {{ $claims->links() }}
    @endif
</x-layouts.account>
