{{-- One owned listing with status, next action and (for drafts/rejections) what is missing. Expects $business, optional $missing --}}
@php
    $status = $business->status;
    $missing ??= [];
@endphp
<li class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
    <a href="{{ route('account.businesses.show', $business->uuid) }}" class="flex min-w-0 flex-1 items-center gap-4">
        <x-business-cover :business="$business" class="aspect-square w-16 shrink-0 rounded-xl" sizes="64px" />
        <span class="min-w-0">
            <span class="flex items-center gap-1.5 font-semibold text-navy-900">
                <span class="truncate">{{ $business->t('name') }}</span>
                @if($business->is_verified)<x-icon name="badge-check" class="size-4 text-gold-600" />@endif
            </span>
            <span class="mt-0.5 block truncate text-xs text-muted-strong">{{ $business->category?->t('name') }}@if($business->locationLabel()) · {{ $business->locationLabel() }}@endif</span>
            <span class="mt-1.5 flex flex-wrap items-center gap-2">
                <x-status-badge :status="$status" />
                <span class="text-xs text-muted-strong">{{ __('account.next.'.$status->value) }}</span>
            </span>
        </span>
    </a>
    <div class="flex shrink-0 items-center gap-2 sm:justify-end">
        @if(in_array($status, [\App\Enums\BusinessStatus::Draft, \App\Enums\BusinessStatus::Rejected], true))
            <a href="{{ route('account.businesses.edit', $business->uuid) }}" class="btn btn-gold btn-sm"><x-icon name="pencil" class="size-3.5" />{{ __('account.next.'.$status->value) }}</a>
        @elseif($business->isPublished())
            <a href="{{ route('business.show', $business) }}" target="_blank" class="btn btn-outline btn-sm"><x-icon name="external-link" class="size-3.5" />{{ __('account.businesses.view_public') }}</a>
        @endif
        <a href="{{ route('account.businesses.show', $business->uuid) }}" class="btn btn-ghost btn-sm">{{ __('account.businesses.open_listing') }}<x-icon name="chevron-right" class="size-3.5" /></a>
    </div>
</li>
@if($status === \App\Enums\BusinessStatus::Rejected && $business->rejection_reason)
    <li class="-mt-2 px-4 pb-4"><p class="rounded-xl border border-danger/15 bg-danger-soft px-3 py-2 text-xs text-danger"><strong>{{ __('account.businesses.rejection_reason') }}:</strong> {{ $business->rejection_reason }}</p></li>
@endif
