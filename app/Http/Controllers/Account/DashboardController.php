<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\BusinessEventType;
use App\Enums\BusinessStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessEvent;
use App\Models\Review;
use App\Services\ListingWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ListingWorkflow $workflow): View
    {
        $user = $request->user();
        $businesses = $user->businesses()->with(['category:id,name_ar,name_en,icon', 'municipality:id,name_ar,name_en', 'area:id,name_ar,name_en,municipality_id', 'images' => fn ($q) => $q->limit(1)])
            ->latest('updated_at')->get();
        $ids = $businesses->pluck('id');

        $since = CarbonImmutable::now()->subDays(29)->startOfDay();
        $events = $ids->isEmpty() ? collect() : BusinessEvent::query()->whereIn('business_id', $ids)->where('created_at', '>=', $since)
            ->selectRaw("type, DATE(created_at) AS day, COUNT(*) AS total")->groupBy('type', 'day')->get();

        $viewsByDay = $events->where('type', BusinessEventType::View)->pluck('total', 'day');
        $labels = [];
        $values = [];
        for ($d = $since; $d->lte(CarbonImmutable::now()); $d = $d->addDay()) {
            $labels[] = $d->locale(app()->getLocale())->isoFormat('D MMM');
            $values[] = (int) ($viewsByDay[$d->toDateString()] ?? 0);
        }

        $stats = [
            'listings' => $businesses->count(),
            'published' => $businesses->filter->isPublished()->count(),
            'views' => array_sum($values),
            'interactions' => (int) $events->filter(fn ($e) => in_array($e->type, BusinessEventType::trackable(), true))->sum('total'),
            'reviews' => $ids->isEmpty() ? 0 : Review::query()->whereIn('business_id', $ids)->where('status', ModerationStatus::Approved)->count(),
        ];

        return view('account.dashboard', [
            'user' => $user,
            'businesses' => $businesses,
            'missing' => $businesses->mapWithKeys(fn ($b) => [$b->id => in_array($b->status, [BusinessStatus::Draft, BusinessStatus::Rejected], true) ? $workflow->missingRequirements($b) : []]),
            'stats' => $stats,
            'chartLabels' => $labels,
            'chartValues' => $values,
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
        ]);
    }
}
