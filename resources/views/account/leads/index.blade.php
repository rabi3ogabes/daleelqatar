@php use App\Enums\QuoteRecipientStatus; @endphp
<x-layouts.account :title="__('quotes.leads.title')" :subtitle="__('quotes.leads.subtitle')" :breadcrumbs="[['label' => __('quotes.leads.title')]]">
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-dash.stat :label="__('quotes.leads.awaiting')" :value="$counts['new']" icon="inbox" />
        <x-dash.stat :label="__('quotes.leads.response_rate')" :value="$responseRate !== null ? $responseRate.'%' : '—'" icon="reply" :sublabel="__('quotes.leads.last_90')" />
        <x-dash.stat :label="__('quotes.leads.reply_time')" :value="$medianMinutes !== null ? \App\Support\Quotes\Duration::human($medianMinutes) : '—'" icon="clock" :sublabel="__('quotes.leads.reply_time_hint')" />
    </div>

    <nav class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-navy-50/70 p-1" aria-label="{{ __('quotes.leads.filter_label') }}">
        @foreach(\App\Http\Controllers\Account\LeadController::FILTERS as $f)
            <a href="{{ route('account.leads.index', ['filter' => $f]) }}" @class(['flex items-center gap-2 whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition', 'bg-white text-navy-900 shadow-sm' => $filter === $f, 'text-muted-strong hover:text-navy-900' => $filter !== $f]) @if($filter === $f) aria-current="page" @endif>
                {{ __('quotes.leads.filter.'.$f) }}<span class="rounded-full bg-navy-100 px-1.5 text-xs tabular-nums">{{ $counts[$f] }}</span>
            </a>
        @endforeach
    </nav>

    @if($leads->isEmpty())
        <x-empty-state icon="inbox" :title="__('quotes.leads.empty_title')" :text="__('quotes.leads.empty_text')" />
    @else
        <div class="card overflow-hidden">
            <ul class="divide-y divide-navy-50">
                @foreach($leads as $lead)
                    @php $q = $lead->quoteRequest; $fresh = $lead->status === QuoteRecipientStatus::Sent; @endphp
                    <li>
                        <a href="{{ route('account.leads.show', $lead->id) }}" class="flex flex-col gap-3 p-4 transition hover:bg-navy-50/50 sm:flex-row sm:items-center">
                            <span @class(['hidden size-2.5 shrink-0 rounded-full sm:block', 'bg-gold-500' => $fresh, 'bg-transparent' => ! $fresh]) aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p @class(['truncate text-navy-900', 'font-bold' => $fresh, 'font-semibold' => ! $fresh])>{{ $q->title }}</p>
                                <p class="mt-0.5 text-xs text-muted-strong">{{ $lead->business?->t('name') }} · {{ $q->category?->t('name') }} · {{ $q->locationLabel() ?? __('quotes.anywhere') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-muted">{{ __('quotes.timing.'.$q->timing) }}</span>
                                <span class="text-xs text-muted">{{ $lead->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                                @if(! $q->isOpen() && ! $lead->status->isAnswered())
                                    <span class="badge badge-muted">{{ __('quotes.status.closed') }}</span>
                                @else
                                    <x-status-badge :status="$lead->status" />
                                @endif
                                <x-icon name="chevron-right" class="size-4 text-muted rtl:rotate-180" />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $leads->links() }}</div>
    @endif
</x-layouts.account>
