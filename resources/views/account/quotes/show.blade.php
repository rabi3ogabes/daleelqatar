@php
    use App\Enums\QuoteRecipientStatus;
    $open = $quote->isOpen();
    $replied = $quote->recipients->where('status', QuoteRecipientStatus::Replied);
    $viewed = $quote->recipients->whereIn('status', [QuoteRecipientStatus::Viewed, QuoteRecipientStatus::Replied, QuoteRecipientStatus::Declined])->count();
    $status = $open ? $quote->status : ($quote->status === \App\Enums\QuoteStatus::Open ? \App\Enums\QuoteStatus::Expired : $quote->status);
@endphp
<x-layouts.account :title="$quote->title" :subtitle="$quote->category?->t('name').' · '.($quote->locationLabel() ?? __('quotes.anywhere'))"
    :breadcrumbs="[['label' => __('quotes.mine.title'), 'url' => route('account.quotes.index')], ['label' => $quote->title]]">
    <x-slot:actions><x-status-badge :status="$status" /></x-slot:actions>

    @if(session('quote_sent'))<div x-init="window.dqForgetQuoteDraft && window.dqForgetQuoteDraft()"></div>@endif

    {{-- Progress --}}
    <div class="mb-6 grid grid-cols-3 gap-3">
        @foreach([
            [__('quotes.progress.sent'), $quote->recipients_count, 'send'],
            [__('quotes.progress.viewed'), $viewed, 'eye'],
            [__('quotes.progress.replied'), $replied->count(), 'reply'],
        ] as [$label, $value, $icon])
            <div class="card p-4">
                <p class="flex items-center gap-1.5 text-xs text-muted-strong"><x-icon :name="$icon" class="size-3.5 text-gold-600" />{{ $label }}</p>
                <p class="mt-1.5 text-2xl font-bold text-navy-900 tabular-nums">{{ format_number($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <h2 class="text-base font-semibold text-navy-900">{{ __('quotes.responses') }}</h2>
            @foreach($quote->recipients as $r)
                @php $b = $r->business; @endphp
                <article @class(['card p-5', 'ring-1 ring-gold-200' => $r->status === QuoteRecipientStatus::Replied])>
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-navy-50 ring-1 ring-navy-100">
                            @if($b?->logoUrl())<img src="{{ $b->logoUrl() }}" alt="" class="size-full object-contain p-1">@else<x-icon name="store" class="size-5 text-muted" />@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($b?->isPublished())
                                    <a href="{{ route('business.show', $b) }}" class="font-semibold text-navy-900 hover:text-gold-800" target="_blank">{{ $b->t('name') }}</a>
                                @else
                                    <span class="font-semibold text-navy-900">{{ $b?->t('name') }}</span>
                                @endif
                                @if($b?->is_verified)<x-icon name="badge-check" class="size-4 text-gold-600" :title="__('business.verified')" />@endif
                                @if($r->status->isAnswered())<x-status-badge :status="$r->status" />@endif
                            </div>
                            <p class="mt-0.5 text-xs text-muted-strong">{{ $b?->locationLabel() }}</p>
                        </div>
                    </div>
                    @if($r->status === QuoteRecipientStatus::Replied)
                        <div class="mt-4 rounded-xl bg-navy-50/60 p-4">
                            @if($r->price_note)
                                <p class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-gold-100 px-3 py-1 text-sm font-semibold text-gold-900"><x-icon name="tag" class="size-3.5" />{{ $r->price_note }}</p>
                            @endif
                            <p class="whitespace-pre-line text-sm leading-relaxed text-charcoal">{{ $r->reply }}</p>
                            <p class="mt-3 text-xs text-muted">{{ __('quotes.replied_at', ['date' => format_date($r->replied_at, 'D MMM, h:mm A')]) }}</p>
                        </div>
                        @if($b?->isPublished())
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($b->whatsappUrl())<a href="{{ $b->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><x-icon name="whatsapp" class="size-4" />{{ __('business.whatsapp') }}</a>@endif
                                @if($b->telUrl())<a href="{{ $b->telUrl() }}" class="btn btn-outline btn-sm"><x-icon name="phone" class="size-4" />{{ __('business.call') }}</a>@endif
                                <a href="{{ route('business.show', $b) }}" target="_blank" class="btn btn-ghost btn-sm">{{ __('quotes.view_profile') }}<x-icon name="external-link" class="size-3.5" /></a>
                            </div>
                        @endif
                    @elseif($r->status === QuoteRecipientStatus::Declined)
                        <p class="mt-3 text-sm text-muted-strong">{{ __('quotes.declined_reason.'.($r->declined_reason ?? 'other')) }}</p>
                    @else
                        <p class="mt-3 flex items-center gap-2 text-sm text-muted-strong">
                            <x-icon name="clock" class="size-4 text-gold-600" />
                            {{ $open ? ($r->status === QuoteRecipientStatus::Viewed ? __('quotes.waiting_viewed') : __('quotes.waiting')) : __('quotes.no_reply') }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>

        <aside class="space-y-4">
            <x-dash.panel :title="__('quotes.your_request')">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.category') }}</dt><dd class="font-medium text-navy-900">{{ $quote->category?->t('name') }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.timing') }}</dt><dd class="font-medium text-navy-900">{{ __('quotes.timing.'.$quote->timing) }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.contact_method') }}</dt><dd class="font-medium text-navy-900">{{ __('quotes.contact.'.$quote->contact_method) }} <span class="text-muted-strong" dir="ltr">{{ $quote->contactValue() }}</span></dd></div>
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.details') }}</dt><dd class="whitespace-pre-line text-charcoal">{{ $quote->details }}</dd></div>
                </dl>
                <p class="mt-4 border-t border-navy-50 pt-3 text-xs text-muted">
                    {{ __('quotes.sent_on', ['date' => format_date($quote->created_at, 'D MMM YYYY, h:mm A')]) }}<br>
                    @if($open){{ __('quotes.open_until', ['date' => format_date($quote->expires_at)]) }}@elseif($quote->closed_at){{ __('quotes.closed_on', ['date' => format_date($quote->closed_at)]) }}@endif
                </p>
            </x-dash.panel>

            @if($open)
                <x-dash.panel :title="__('quotes.close_title')">
                    <p class="mb-3 text-sm text-muted-strong">{{ __('quotes.close_text') }}</p>
                    <form method="POST" action="{{ route('account.quotes.close', $quote->uuid) }}" class="space-y-3">@csrf
                        <x-form.select name="reason" :options="collect(\App\Models\QuoteRequest::CLOSE_REASONS)->mapWithKeys(fn ($r) => [$r => __('quotes.close_reason.'.$r)])->all()" value="found" />
                        <button class="btn btn-outline w-full"><x-icon name="circle-check" class="size-4" />{{ __('quotes.close') }}</button>
                    </form>
                </x-dash.panel>
            @else
                <a href="{{ route('quotes.create', ['category' => $quote->category?->slug]) }}" class="card card-hover flex items-center gap-3 p-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-gold-50 text-gold-700"><x-icon name="rotate-ccw" class="size-5" /></span>
                    <span class="flex-1 text-sm font-semibold text-navy-900">{{ __('quotes.send_again') }}</span>
                    <x-icon name="chevron-right" class="size-4 text-muted rtl:rotate-180" />
                </a>
            @endif
        </aside>
    </div>
</x-layouts.account>
