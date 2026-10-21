<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ListingReport;
use App\Models\ReviewReport;
use App\Services\AuditLogger;
use App\Support\AdminNavigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'reviews' ? 'reviews' : 'listings';
        $status = ReportStatus::tryFrom((string) $request->query('status', 'open'));

        $listing = ListingReport::query()->with(['business:id,name_ar,name_en,slug,status', 'user:id,name', 'handler:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))->latest()->paginate(15, ['*'], 'lp')->withQueryString();
        $review = ReviewReport::query()->with(['review' => fn ($q) => $q->with('business:id,name_ar,name_en,slug', 'user:id,name'), 'user:id,name', 'handler:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))->latest()->paginate(15, ['*'], 'rp')->withQueryString();

        return view('admin.reports.index', [
            'tab' => $tab,
            'status' => $status,
            'listingReports' => $listing,
            'reviewReports' => $review,
            'openCounts' => [
                'listings' => ListingReport::query()->where('status', 'open')->count(),
                'reviews' => ReviewReport::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    public function resolveListing(Request $request, ListingReport $report, AuditLogger $audit): RedirectResponse
    {
        return $this->resolve($request, $report, $audit, 'listing_report');
    }

    public function resolveReview(Request $request, ReviewReport $report, AuditLogger $audit): RedirectResponse
    {
        return $this->resolve($request, $report, $audit, 'review_report');
    }

    private function resolve(Request $request, Model $report, AuditLogger $audit, string $kind): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ReportStatus::Resolved->value, ReportStatus::Dismissed->value, ReportStatus::Open->value])],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $old = $report->status->value;
        $report->forceFill([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? null,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();
        $audit->log($kind.'.'.$data['status'], $report, ['status' => $old], ['status' => $data['status']], array_filter(['note' => $data['resolution_note'] ?? null]));
        AdminNavigation::flush();

        return back()->with('success', __('admin.reports.updated'));
    }
}
