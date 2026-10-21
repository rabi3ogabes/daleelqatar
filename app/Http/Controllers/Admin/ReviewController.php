<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Services\ReviewService;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $reviews = Review::query()
            ->with(['business:id,name_ar,name_en,slug', 'user:id,name,email', 'moderator:id,name', 'reports' => fn ($q) => $q->latest()->limit(3)])
            ->when(ModerationStatus::tryFrom((string) $status), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('reported') === '1', fn ($q) => $q->where('reports_count', '>', 0))
            ->when($rating = $request->integer('rating'), fn ($q) => $q->where('rating', $rating))
            ->when(trim((string) $request->query('q')), fn ($q, $term) => $q->where(fn ($w) => $w->where('body', 'ilike', '%'.addcslashes($term, '%_\\').'%')->orWhere('title', 'ilike', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('reports_count')->latest()->paginate(15)->withQueryString();

        $counts = Review::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.reviews.index', ['reviews' => $reviews, 'status' => $status, 'counts' => $counts]);
    }

    public function moderate(Request $request, Review $review, ReviewService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ModerationStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($review->trashed()) {
            $review->restore();
        }
        $service->moderate($review, $request->user(), ModerationStatus::from($data['status']), $data['note'] ?? null);
        AdminNavigation::flush();

        return back()->with('success', __('admin.reviews.moderated', ['status' => ModerationStatus::from($data['status'])->label()]));
    }

    public function destroy(Request $request, Review $review, ReviewService $service, AuditLogger $audit): RedirectResponse
    {
        $audit->log('review.deleted', $review, ['rating' => $review->rating, 'business_id' => $review->business_id]);
        $business = $review->business;
        $review->delete();
        if ($business) {
            $service->refreshRating($business);
        }
        AdminNavigation::flush();

        return back()->with('success', __('common.deleted'));
    }
}
