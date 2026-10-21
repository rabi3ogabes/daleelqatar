<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    use ResolvesApiModels;

    public function __construct(private readonly ReviewService $reviews) {}

    public function store(Request $request, string $business): JsonResponse
    {
        $model = $this->findPublishedBusiness($business);
        $this->authorize('create', [Review::class, $model]);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:'.(int) setting('reviews.min_length', 20), 'max:3000'],
        ]);

        $review = $this->reviews->submit($model, $request->user(), $data);
        $review->load('user:id,name', 'business');

        return (new ReviewResource($review))->withStatus()->additional([
            'message' => $review->status === ModerationStatus::Approved ? __('reviews.submitted') : __('reviews.submitted_pending'),
        ])->response()->setStatusCode(201);
    }

    public function report(Request $request, string $review): JsonResponse
    {
        $model = $this->findReview($review);
        abort_unless($model->status === ModerationStatus::Approved, 404);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)->only(ReportReason::forReviews())],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->reviews->report($model, $request->user(), ReportReason::from($data['reason']), $data['details'] ?? null);

        return response()->json(['message' => __('reviews.reported')], 201);
    }

    /** Reviews written by the current user (any status). */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $reviews = $request->user()->reviews()->with(['business', 'user:id,name'])->latest()->paginate(15);
        $reviews->getCollection()->transform(fn ($r) => (new ReviewResource($r))->withStatus());

        return ReviewResource::collection($reviews);
    }

    public function destroy(Request $request, string $review): Response
    {
        $model = $this->findReview($review);
        abort_unless($model->user_id === $request->user()->id, 403);

        $business = $model->business;
        $model->delete();
        if ($business) {
            $this->reviews->refreshRating($business);
        }

        return response()->noContent();
    }

    /** Approved reviews received on the businesses the user owns. */
    public function received(Request $request): AnonymousResourceCollection
    {
        $reviews = Review::query()->approved()
            ->whereIn('business_id', $request->user()->businesses()->select('id'))
            ->with(['business', 'user:id,name'])->latest()->paginate(15);
        $reviews->getCollection()->transform(fn ($r) => (new ReviewResource($r))->withStatus());

        return ReviewResource::collection($reviews);
    }

    public function reply(Request $request, string $review): ReviewResource
    {
        $model = $this->findReview($review);
        $this->authorize('reply', $model);

        $data = $request->validate(['owner_reply' => ['nullable', 'string', 'max:2000']]);
        $this->reviews->reply($model, $request->user(), $data['owner_reply'] ?? null);

        return (new ReviewResource($model->load('user:id,name', 'business')))->withStatus();
    }
}
