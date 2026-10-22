<x-layouts.account :title="__('quotes.mine.title')" :subtitle="__('quotes.mine.subtitle')" :breadcrumbs="[['label' => __('quotes.mine.title')]]">
    <x-slot:actions>
        <a href="{{ route('quotes.create') }}" class="btn btn-gold"><x-icon name="plus" class="size-4" />{{ __('quotes.new_request') }}</a>
    </x-slot:actions>

    @if($quotes->isEmpty())
        <x-empty-state icon="message-square-quote" :title="__('quotes.mine.empty_title')" :text="__('quotes.mine.empty_text')">
            <a href="{{ route('quotes.create') }}" class="btn btn-primary"><x-icon name="send" class="size-4" />{{ __('quotes.new_request') }}</a>
        </x-empty-state>
    @else
        <div class="card overflow-hidden">
            <ul class="divide-y divide-navy-50">
                @foreach($quotes as $quote)
                    @php $status = $quote->isOpen() ? $quote->status : ($quote->status === \App\Enums\QuoteStatus::Open ? \App\Enums\QuoteStatus::Expired : $quote->status); @endphp
                    <li>
                        <a href="{{ route('account.quotes.show', $quote->uuid) }}" class="flex flex-col gap-3 p-4 transition hover:bg-navy-50/50 sm:flex-row sm:items-center">
                            <span class="hidden size-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-700 ring-1 ring-gold-200 sm:flex"><x-icon :name="$quote->category?->icon ?: 'message-square-quote'" class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-navy-900">{{ $quote->title }}</p>
                                <p class="mt-0.5 text-xs text-muted-strong">{{ $quote->category?->t('name') }} · {{ $quote->locationLabel() ?? __('quotes.anywhere') }} · {{ format_date($quote->created_at) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-success-soft text-success' => $quote->replies_count > 0, 'bg-navy-50 text-muted-strong' => $quote->replies_count === 0])>
                                    <x-icon name="reply" class="size-3.5" />{{ trans_choice('quotes.replies_count', $quote->replies_count, ['count' => $quote->replies_count]) }}
                                </span>
                                <x-status-badge :status="$status" />
                                <x-icon name="chevron-right" class="size-4 text-muted rtl:rotate-180" />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $quotes->links() }}</div>
    @endif
</x-layouts.account>
