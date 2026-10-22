<x-layouts.dashboard area="admin" :title="__('quotes.admin.title')" :subtitle="__('quotes.admin.subtitle')" :nav="\App\Support\AdminNavigation::for(auth()->user())"
    :breadcrumbs="[['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')], ['label' => __('quotes.admin.title')]]">
    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-dash.stat :label="__('quotes.admin.sent_30')" :value="$stats['sent']" icon="send" :sublabel="__('quotes.admin.open_now', ['count' => format_number($stats['open'])])" />
        <x-dash.stat :label="__('quotes.admin.answered_share')" :value="$stats['answered_share'] !== null ? $stats['answered_share'].'%' : '—'" icon="circle-check" :sublabel="__('quotes.admin.answered_hint')" />
        <x-dash.stat :label="__('quotes.admin.reply_rate')" :value="$stats['reply_rate'] !== null ? $stats['reply_rate'].'%' : '—'" icon="reply" :sublabel="__('quotes.admin.reply_rate_hint')" />
        <x-dash.stat :label="__('quotes.leads.reply_time')" :value="$stats['median_minutes'] !== null ? \App\Support\Quotes\Duration::human($stats['median_minutes']) : '—'" icon="clock" :sublabel="__('quotes.leads.reply_time_hint')" />
    </div>

    @if($topCategories->isNotEmpty())
        <x-dash.panel :title="__('quotes.admin.top_categories')" :subtitle="__('dashboard.last_30_days')" class="mb-6">
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($topCategories as $c)
                    @php $share = $c->total ? (int) round($c->answered / $c->total * 100) : 0; @endphp
                    <li class="rounded-xl border border-navy-100 p-3">
                        <div class="flex items-center justify-between gap-2 text-sm"><span class="truncate font-medium text-navy-900">{{ app()->getLocale() === 'ar' ? $c->name_ar : $c->name_en }}</span><span class="tabular-nums text-muted-strong">{{ format_number($c->total) }}</span></div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ $share >= 50 ? 'bg-success' : 'bg-gold-500' }}" style="width: {{ $share }}%"></div></div>
                        <p class="mt-1 text-xs text-muted">{{ __('quotes.admin.answered_pct', ['pct' => $share]) }}</p>
                    </li>
                @endforeach
            </ul>
        </x-dash.panel>
    @endif

    <x-dash.filters :action="route('admin.quotes.index')">
        <x-form.input name="q" :value="$q" :label="__('common.search')" icon="search" class="w-full sm:w-64" :placeholder="__('quotes.admin.search_placeholder')" />
        <x-form.select name="status" :label="__('common.status')" :value="$status?->value" :placeholder="__('common.all')"
            :options="collect(\App\Enums\QuoteStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-form.select name="category" :label="__('quotes.form.category')" :value="$categoryId" :placeholder="__('common.all')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->t('name')])->all()" class="w-full sm:w-56" />
    </x-dash.filters>

    <x-dash.panel :padding="false">
        @if($quotes->isEmpty())
            <x-empty-state icon="message-square-quote" :title="__('quotes.admin.empty')" class="m-5" />
        @else
            <x-dash.table>
                <thead><tr><th>{{ __('quotes.form.title') }}</th><th>{{ __('quotes.admin.customer') }}</th><th>{{ __('quotes.admin.replies') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.status') }}</th><th class="!text-end">{{ __('common.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($quotes as $quote)
                        <tr>
                            <td class="max-w-xs"><span class="block truncate font-semibold text-navy-900">{{ $quote->title }}</span><span class="block text-xs text-muted-strong">{{ $quote->category?->t('name') }} · {{ $quote->locationLabel() ?? __('quotes.anywhere') }}</span></td>
                            <td><span class="text-navy-900">{{ $quote->user?->name }}</span><span class="block text-xs text-muted-strong" dir="ltr">{{ $quote->user?->email }}</span></td>
                            <td class="tabular-nums">{{ $quote->replies_count }}/{{ $quote->recipients_count }}</td>
                            <td class="text-xs text-muted-strong">{{ format_date($quote->created_at) }}</td>
                            <td><x-status-badge :status="$quote->status" /></td>
                            <td class="text-end"><a href="{{ route('admin.quotes.show', $quote->uuid) }}" class="btn btn-ghost btn-sm">{{ __('admin.review') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-dash.table>
            <div class="px-5 pb-5">{{ $quotes->links() }}</div>
        @endif
    </x-dash.panel>
</x-layouts.dashboard>
