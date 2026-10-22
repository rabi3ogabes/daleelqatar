<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuoteRecipientStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\QuoteRecipient;
use App\Models\QuoteRequest;
use App\Support\Quotes\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Oversight of quote requests: volume, answer rate, reply speed, and removing abuse. */
class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $status = QuoteStatus::tryFrom((string) $request->query('status', ''));
        $categoryId = (int) $request->query('category') ?: null;
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);

        $quotes = QuoteRequest::query()
            ->with(['user:id,name,email', 'category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id'])
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($categoryId, fn ($w) => $w->where('category_id', $categoryId))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('title', 'ILIKE', '%'.addcslashes($q, '%_\\').'%')
                ->orWhereHas('user', fn ($u) => $u->where('email', 'ILIKE', '%'.addcslashes($q, '%_\\').'%'))))
            ->latest()->paginate(20)->withQueryString();

        $since = now()->subDays(30);
        $sent30 = QuoteRequest::query()->where('created_at', '>=', $since)->count();
        $recipients30 = QuoteRecipient::query()->where('created_at', '>=', $since);
        $delivered = (clone $recipients30)->count();
        $replied = (clone $recipients30)->where('status', QuoteRecipientStatus::Replied)->count();
        $answeredRequests = QuoteRequest::query()->where('created_at', '>=', $since)->where('replies_count', '>', 0)->count();
        $medianMinutes = (clone $recipients30)->whereNotNull('replied_at')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (replied_at - created_at)) / 60) AS m')->value('m');

        $topCategories = DB::table('quote_requests')->where('quote_requests.created_at', '>=', $since)
            ->join('categories', 'categories.id', '=', 'quote_requests.category_id')
            ->selectRaw('categories.id, categories.name_ar, categories.name_en, COUNT(*) AS total, SUM(CASE WHEN quote_requests.replies_count > 0 THEN 1 ELSE 0 END) AS answered')
            ->groupBy('categories.id', 'categories.name_ar', 'categories.name_en')->orderByDesc('total')->limit(6)->get();

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'status' => $status,
            'categoryId' => $categoryId,
            'q' => $q,
            'categories' => Category::query()->active()->ordered()->get(['id', 'name_ar', 'name_en', 'parent_id']),
            'stats' => [
                'sent' => $sent30,
                'answered_share' => $sent30 ? (int) round($answeredRequests / $sent30 * 100) : null,
                'reply_rate' => $delivered ? (int) round($replied / $delivered * 100) : null,
                'median_minutes' => $medianMinutes !== null ? (int) round((float) $medianMinutes) : null,
                'open' => QuoteRequest::query()->open()->count(),
            ],
            'topCategories' => $topCategories,
        ]);
    }

    public function show(QuoteRequest $quote): View
    {
        $quote->load(['user', 'category', 'municipality', 'area', 'originBusiness:id,name_ar,name_en,slug',
            'recipients.business' => fn ($q) => $q->with('owner:id,name,email')]);

        return view('admin.quotes.show', ['quote' => $quote]);
    }

    public function remove(Request $request, QuoteRequest $quote, QuoteService $quotes): RedirectResponse
    {
        $quotes->remove($quote, $request->user());

        return redirect()->route('admin.quotes.show', $quote->uuid)->with('success', __('quotes.admin.removed'));
    }
}
