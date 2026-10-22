<x-layouts.dashboard area="admin" :title="$quote->title" :subtitle="$quote->category?->t('name').' · '.($quote->locationLabel() ?? __('quotes.anywhere'))" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('quotes.admin.title'), 'url' => route('admin.quotes.index')], ['label' => $quote->title]]">
    <x-slot:actions><x-status-badge :status="$quote->status" /></x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-dash.panel :title="__('quotes.lead.request')">
                <p class="whitespace-pre-line text-sm leading-relaxed text-charcoal">{{ $quote->details }}</p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.timing') }}</dt><dd class="text-navy-900">{{ __('quotes.timing.'.$quote->timing) }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('quotes.form.contact_method') }}</dt><dd class="text-navy-900">{{ __('quotes.contact.'.$quote->contact_method) }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('quotes.admin.origin') }}</dt><dd class="text-navy-900">{{ $quote->originBusiness?->t('name') ?? '—' }}</dd></div>
                </dl>
            </x-dash.panel>
            <x-dash.panel :title="__('quotes.admin.recipients')" :padding="false">
                <x-dash.table>
                    <thead><tr><th>{{ __('admin.businesses.business') }}</th><th>{{ __('quotes.admin.owner') }}</th><th>{{ __('common.status') }}</th><th>{{ __('quotes.admin.answer') }}</th></tr></thead>
                    <tbody>
                        @foreach($quote->recipients as $r)
                            <tr>
                                <td><a href="{{ route('admin.businesses.show', $r->business_id) }}" class="font-semibold text-navy-900 hover:text-gold-800">{{ $r->business?->t('name') }}</a></td>
                                <td class="text-xs"><span class="text-navy-900">{{ $r->business?->owner?->name }}</span><span class="block text-muted-strong" dir="ltr">{{ $r->business?->owner?->email }}</span></td>
                                <td><x-status-badge :status="$r->status" /></td>
                                <td class="max-w-sm text-xs text-muted-strong">
                                    @if($r->reply)<span class="line-clamp-3">{{ $r->reply }}</span>@if($r->price_note)<span class="mt-1 block font-semibold text-gold-800">{{ $r->price_note }}</span>@endif
                                    @elseif($r->declined_reason){{ __('quotes.declined_reason.'.$r->declined_reason) }}@else—@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-dash.table>
            </x-dash.panel>
        </div>
        <aside class="space-y-4">
            <x-dash.panel :title="__('quotes.admin.customer')">
                <p class="font-semibold text-navy-900">{{ $quote->user?->name }}</p>
                <p class="text-xs text-muted-strong" dir="ltr">{{ $quote->user?->email }}</p>
                @if($quote->contact_phone)<p class="mt-1 text-xs text-muted-strong" dir="ltr">{{ $quote->contact_phone }}</p>@endif
                <p class="mt-3 text-xs text-muted">{{ __('quotes.sent_on', ['date' => format_date($quote->created_at, 'D MMM YYYY, h:mm A')]) }}</p>
            </x-dash.panel>
            @if($quote->status !== \App\Enums\QuoteStatus::Removed)
                <x-dash.panel :title="__('quotes.admin.remove_title')">
                    <p class="mb-3 text-sm text-muted-strong">{{ __('quotes.admin.remove_text') }}</p>
                    <form method="POST" action="{{ route('admin.quotes.remove', $quote->uuid) }}" data-confirm="{{ __('quotes.admin.remove_confirm') }}">@csrf
                        <button class="btn btn-danger-outline w-full"><x-icon name="ban" class="size-4" />{{ __('quotes.admin.remove') }}</button>
                    </form>
                </x-dash.panel>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
