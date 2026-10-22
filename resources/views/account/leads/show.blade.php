@php
    use App\Enums\QuoteRecipientStatus;
    $open = $quote->isOpen();
    $customer = $quote->user;
@endphp
<x-layouts.account :title="$quote->title" :subtitle="__('quotes.lead.for', ['business' => $lead->business?->t('name')])"
    :breadcrumbs="[['label' => __('quotes.leads.title'), 'url' => route('account.leads.index')], ['label' => $quote->title]]">
    <x-slot:actions><x-status-badge :status="$lead->status" /></x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-dash.panel :title="__('quotes.lead.request')">
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="badge badge-muted"><x-icon :name="$quote->category?->icon ?: 'layers'" class="size-3.5" />{{ $quote->category?->t('name') }}</span>
                    <span class="badge badge-muted"><x-icon name="map-pin" class="size-3.5" />{{ $quote->locationLabel() ?? __('quotes.anywhere') }}</span>
                    <span class="badge badge-warning"><x-icon name="clock" class="size-3.5" />{{ __('quotes.timing.'.$quote->timing) }}</span>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-charcoal">{{ $quote->details }}</p>
                <p class="mt-4 text-xs text-muted">{{ __('quotes.received_on', ['date' => format_date($lead->created_at, 'D MMM YYYY, h:mm A')]) }} · {{ __('quotes.lead.shared_with', ['count' => $quote->recipients_count]) }}</p>
            </x-dash.panel>

            @if($lead->status === QuoteRecipientStatus::Replied)
                <x-dash.panel :title="__('quotes.lead.your_reply')">
                    @if($lead->price_note)<p class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-gold-100 px-3 py-1 text-sm font-semibold text-gold-900"><x-icon name="tag" class="size-3.5" />{{ $lead->price_note }}</p>@endif
                    <p class="whitespace-pre-line text-sm text-charcoal">{{ $lead->reply }}</p>
                    <p class="mt-3 text-xs text-muted">{{ __('quotes.replied_at', ['date' => format_date($lead->replied_at, 'D MMM, h:mm A')]) }}</p>
                </x-dash.panel>
            @endif

            @if($open && $lead->status !== QuoteRecipientStatus::Declined)
                <x-dash.panel :title="$lead->status === QuoteRecipientStatus::Replied ? __('quotes.lead.edit_reply') : __('quotes.lead.reply_title')" :subtitle="__('quotes.lead.reply_hint')">
                    <form method="POST" action="{{ route('account.leads.reply', $lead->id) }}" class="space-y-4" x-data="{ len: {{ mb_strlen((string) old('reply', $lead->reply)) }} }">@csrf
                        <x-form.textarea name="reply" :label="__('quotes.lead.reply_label')" rows="6" required maxlength="2000" :value="$lead->reply" :placeholder="__('quotes.lead.reply_placeholder')" @input="len = $event.target.value.length" />
                        <p class="-mt-3 text-end text-xs tabular-nums text-muted"><span x-text="len"></span>/2000</p>
                        <x-form.input name="price_note" :label="__('quotes.lead.price_label')" maxlength="120" :value="$lead->price_note" :hint="__('quotes.lead.price_hint')" :placeholder="__('quotes.lead.price_placeholder')" />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-muted-strong">{{ __('quotes.lead.reply_notice') }}</p>
                            <button class="btn btn-gold"><x-icon name="send" class="size-4" />{{ $lead->status === QuoteRecipientStatus::Replied ? __('quotes.lead.update') : __('quotes.lead.send') }}</button>
                        </div>
                    </form>
                </x-dash.panel>
            @elseif(! $open)
                <p class="flex items-center gap-2 rounded-2xl bg-navy-50 p-4 text-sm text-muted-strong"><x-icon name="info" class="size-4" />{{ __('quotes.lead.closed_note') }}</p>
            @endif
        </div>

        <aside class="space-y-4">
            <x-dash.panel :title="__('quotes.lead.customer')">
                <p class="font-semibold text-navy-900">{{ $customer?->name }}</p>
                <p class="mt-1 text-xs text-muted-strong">{{ __('quotes.lead.prefers', ['method' => __('quotes.contact.'.$quote->contact_method)]) }}</p>
                @if($quote->contactValue())
                    <a href="{{ $quote->contactUrl() }}" @if($quote->contact_method === 'whatsapp') target="_blank" rel="noopener" @endif class="btn btn-primary mt-4 w-full">
                        <x-icon :name="['whatsapp' => 'whatsapp', 'phone' => 'phone', 'email' => 'mail'][$quote->contact_method]" class="size-4" />
                        <span dir="ltr">{{ $quote->contactValue() }}</span>
                    </a>
                @endif
                <p class="mt-3 text-xs text-muted">{{ __('quotes.lead.privacy') }}</p>
            </x-dash.panel>

            @if($open && ! $lead->status->isAnswered())
                <x-dash.panel :title="__('quotes.lead.decline_title')">
                    <form method="POST" action="{{ route('account.leads.decline', $lead->id) }}" class="space-y-3" data-confirm="{{ __('quotes.lead.decline_confirm') }}" data-confirm-tone="neutral">@csrf
                        <x-form.select name="reason" :options="collect(\App\Models\QuoteRecipient::DECLINE_REASONS)->mapWithKeys(fn ($r) => [$r => __('quotes.declined_reason.'.$r)])->all()" value="not_available" />
                        <button class="btn btn-outline w-full">{{ __('quotes.lead.decline') }}</button>
                    </form>
                </x-dash.panel>
            @endif
        </aside>
    </div>
</x-layouts.account>
