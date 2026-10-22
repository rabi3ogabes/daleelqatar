<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Support\Insights\BusinessInsights;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** How a listing performs: views, search appearances, contacts, QR scans and requests, with clear next steps. */
class InsightsController extends Controller
{
    public function show(Request $request, Business $business, BusinessInsights $insights): View
    {
        abort_unless($business->isOwnedBy($request->user()), 404);

        $period = in_array($request->query('period'), BusinessInsights::PERIODS, true) ? $request->query('period') : '30d';
        [$from, $to, $prevFrom, $prevTo] = BusinessInsights::range($period);

        $current = $insights->totals($business, $from, $to);
        $previous = $insights->totals($business, $prevFrom, $prevTo);
        $average = $insights->categoryAverage($business, $from, $to);
        $contacts = BusinessInsights::contacts($current);

        return view('account.insights.show', [
            'business' => $business->load(['category:id,name_ar,name_en', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id']),
            'period' => $period,
            'from' => $from,
            'to' => $to->subDay(),
            'current' => $current,
            'previous' => $previous,
            'contacts' => $contacts,
            'previousContacts' => BusinessInsights::contacts($previous),
            'conversion' => $current['views'] > 0 ? round($contacts / $current['views'] * 100, 1) : null,
            'average' => $average,
            'daily' => $insights->daily($business, $from, $to),
            'tips' => $business->isPublished() ? $insights->tips($business, $current) : [],
        ]);
    }
}
