<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\QuoteRecipientStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\QuoteRecipient;
use App\Support\Quotes\QuoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The business owner's inbox of quote requests sent to their listings. */
class LeadController extends Controller
{
    public const FILTERS = ['new', 'replied', 'all'];

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'new';

        $base = $this->mine($request);
        $counts = [
            'new' => (clone $base)->whereIn('quote_request_recipients.status', [QuoteRecipientStatus::Sent, QuoteRecipientStatus::Viewed])
                ->whereHas('quoteRequest', fn (Builder $q) => $q->open())->count(),
            'replied' => (clone $base)->where('quote_request_recipients.status', QuoteRecipientStatus::Replied)->count(),
            'all' => (clone $base)->count(),
        ];

        $leads = $base
            ->when($filter === 'new', fn ($q) => $q->whereIn('quote_request_recipients.status', [QuoteRecipientStatus::Sent, QuoteRecipientStatus::Viewed])
                ->whereHas('quoteRequest', fn (Builder $r) => $r->open()))
            ->when($filter === 'replied', fn ($q) => $q->where('quote_request_recipients.status', QuoteRecipientStatus::Replied))
            ->with(['business:id,uuid,name_ar,name_en,slug', 'quoteRequest' => fn ($q) => $q->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id'])])
            ->latest('quote_request_recipients.created_at')->paginate(15)->withQueryString();

        // Answer rate and typical reply time over the last 90 days, shown as gentle encouragement.
        $recent = (clone $this->mine($request))->where('quote_request_recipients.created_at', '>=', now()->subDays(90));
        $total = (clone $recent)->count();
        $answered = (clone $recent)->where('quote_request_recipients.status', QuoteRecipientStatus::Replied)->count();
        $medianMinutes = (clone $recent)->whereNotNull('replied_at')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (replied_at - quote_request_recipients.created_at)) / 60) AS m')
            ->value('m');

        return view('account.leads.index', [
            'leads' => $leads,
            'filter' => $filter,
            'counts' => $counts,
            'responseRate' => $total ? (int) round($answered / $total * 100) : null,
            'medianMinutes' => $medianMinutes !== null ? (int) round((float) $medianMinutes) : null,
        ]);
    }

    public function show(Request $request, QuoteRecipient $lead, QuoteService $quotes): View
    {
        $this->authorizeLead($request, $lead);
        $lead->load(['business', 'quoteRequest' => fn ($q) => $q->with(['user:id,name,email,phone', 'category', 'municipality', 'area'])]);
        abort_if($lead->quoteRequest->status === QuoteStatus::Removed, 404);

        $quotes->markViewed($lead);

        return view('account.leads.show', ['lead' => $lead, 'quote' => $lead->quoteRequest]);
    }

    public function reply(Request $request, QuoteRecipient $lead, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeLead($request, $lead);
        abort_unless($lead->quoteRequest->isOpen(), 403, __('quotes.errors.closed'));

        $data = $request->validate([
            'reply' => ['required', 'string', 'min:10', 'max:2000'],
            'price_note' => ['nullable', 'string', 'max:120'],
        ], [], ['reply' => __('quotes.lead.reply_label'), 'price_note' => __('quotes.lead.price_label')]);

        $quotes->reply($lead, $data['reply'], $data['price_note'] ?? null);

        return redirect()->route('account.leads.show', $lead->id)->with('success', __('quotes.lead.replied_done'));
    }

    public function decline(Request $request, QuoteRecipient $lead, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate(['reason' => ['required', Rule::in(QuoteRecipient::DECLINE_REASONS)]]);

        $quotes->decline($lead, $data['reason']);

        return redirect()->route('account.leads.index')->with('success', __('quotes.lead.declined_done'));
    }

    private function mine(Request $request): Builder
    {
        return QuoteRecipient::query()
            ->whereIn('quote_request_recipients.business_id', $request->user()->businesses()->select('id'))
            ->whereHas('quoteRequest', fn (Builder $q) => $q->where('status', '!=', QuoteStatus::Removed));
    }

    private function authorizeLead(Request $request, QuoteRecipient $lead): void
    {
        abort_unless($lead->business && $lead->business->owner_id === $request->user()->id, 404);
    }
}
