<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ListingReport;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request, Business $business): RedirectResponse
    {
        abort_unless($business->isPublished(), 404);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1500'],
            'reporter_email' => ['nullable', 'email:rfc', 'max:191'],
            'company_website' => ['prohibited'], // honeypot
        ]);

        $report = new ListingReport([
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'reporter_email' => $request->user()?->email ?? ($data['reporter_email'] ?? null),
        ]);
        $report->business_id = $business->id;
        $report->user_id = $request->user()?->id;
        $report->ip_hash = ip_hash();
        $report->status = 'open';
        $report->save();

        Notification::send(User::permission('reports.handle')->get(), new StaffAlert('report', __('notifications.staff_report_body', ['business' => $business->t('name')]), 'admin.reports.index'));

        return back()->with('success', __('business.report_sent'));
    }
}
