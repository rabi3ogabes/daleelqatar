<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetrics;
use App\Support\ChartPalette;
use App\Support\Enrichment\DataQuality;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics): View
    {
        $approvals = $metrics->approvalsByMonth();

        return view('admin.dashboard', [
            'totals' => $metrics->totals(),
            'trends' => $metrics->trends(),
            'months' => $metrics->monthLabels(),
            'growth' => $metrics->businessGrowth(),
            'approvalSeries' => [
                ['label' => __('admin.dashboard.approved'), 'values' => $approvals['approved'], 'color' => ChartPalette::SUCCESS],
                ['label' => __('admin.dashboard.rejected'), 'values' => $approvals['rejected'], 'color' => ChartPalette::DANGER],
            ],
            'distribution' => $metrics->categoryDistribution(),
            'daily' => $metrics->dailyActivity(),
            'recentUsers' => $metrics->recentUsers(),
            'recentSubmissions' => $metrics->recentSubmissions(),
            'activity' => $metrics->recentActivity(),
            'topSearches' => $metrics->topSearches(),
            'quality' => auth()->user()->can('imports.manage') ? DataQuality::stats() : null,
        ]);
    }
}
