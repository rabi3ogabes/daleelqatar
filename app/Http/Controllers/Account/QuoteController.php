<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Support\Quotes\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The customer's side: requests they sent and the replies they received. */
class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $quotes = QuoteRequest::query()->where('user_id', $request->user()->id)
            ->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id'])
            ->latest()->paginate(10);

        return view('account.quotes.index', ['quotes' => $quotes]);
    }

    public function show(Request $request, QuoteRequest $quote): View
    {
        abort_unless($quote->user_id === $request->user()->id, 404);

        $quote->load(['category', 'municipality', 'area',
            'recipients' => fn ($q) => $q->orderByRaw("CASE status WHEN 'replied' THEN 0 WHEN 'viewed' THEN 1 WHEN 'sent' THEN 2 ELSE 3 END")->orderBy('replied_at'),
            'recipients.business' => fn ($q) => $q->with(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id'])]);

        return view('account.quotes.show', ['quote' => $quote]);
    }

    public function close(Request $request, QuoteRequest $quote, QuoteService $quotes): RedirectResponse
    {
        abort_unless($quote->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', Rule::in(QuoteRequest::CLOSE_REASONS)]]);

        $quotes->close($quote, $data['reason']);

        return back()->with('success', __('quotes.closed_done'));
    }
}
