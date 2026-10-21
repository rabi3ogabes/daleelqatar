<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, Business $business, ReviewService $reviews): RedirectResponse
    {
        $this->authorize('create', [Review::class, $business]);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:'.(int) setting('reviews.min_length', 20), 'max:3000'],
        ]);

        $review = $reviews->submit($business, $request->user(), $data);

        return redirect()->to(route('business.show', $business).'#reviews')->with('success',
            $review->status === ModerationStatus::Approved ? __('reviews.submitted') : __('reviews.submitted_pending'));
    }

    public function report(Request $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        abort_unless($review->status === ModerationStatus::Approved, 404);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)->only(ReportReason::forReviews())],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviews->report($review, $request->user(), ReportReason::from($data['reason']), $data['details'] ?? null);

        return back()->with('success', __('reviews.reported'));
    }
}
