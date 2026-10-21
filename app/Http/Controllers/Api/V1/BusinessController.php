<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BusinessSearchEngine;
use App\Enums\BusinessEventType;
use App\Enums\ReportReason;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\BusinessSummaryResource;
use App\Http\Resources\ReviewResource;
use App\Models\Business;
use App\Models\ListingReport;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Services\AnalyticsRecorder;
use App\Support\Directory;
use App\Support\Search\SearchCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    use ResolvesApiModels;

    /**
     * Directory search. Filters: q, category, municipality, area, verified, rating,
     * open_now, featured, sort (relevance|newest|rating|popular|name), per_page (≤48), page.
     */
    public function index(Request $request, BusinessSearchEngine $engine, AnalyticsRecorder $analytics): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:140'],
            'municipality' => ['nullable', 'string', 'max:140'],
            'area' => ['nullable', 'string', 'max:140'],
            'verified' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'open_now' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'featured' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'sort' => ['nullable', Rule::in(SearchCriteria::SORTS)],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $criteria = SearchCriteria::fromRequest($request, 12, 48);
        $results = $engine->search($criteria);
        $analytics->recordSearch($criteria, $results->total(), 'api');

        return BusinessSummaryResource::collection($results)->additional(['meta' => [
            'filters' => array_merge(['sort' => $criteria->sort], $criteria->toQuery()),
        ]]);
    }

    public function featured(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(48, $request->integer('per_page', 12)));

        return BusinessSummaryResource::collection(
            Business::query()->published()->featured()->with(Directory::cardRelations())
                ->orderByDesc('is_verified')->latest('published_at')->paginate($perPage)
        );
    }

    public function show(string $business, AnalyticsRecorder $analytics): BusinessResource
    {
        $model = $this->findVisibleBusiness($business);
        $model->load([
            'category', 'categories', 'municipality', 'area', 'images', 'services', 'openingHours', 'socialLinks',
            'activeOffers' => fn ($q) => $q->orderBy('ends_at'),
        ]);

        if ($model->isPublished()) {
            $analytics->recordView($model, 'api');
        }

        $user = auth('sanctum')->user();
        $favorited = $user ? DB::table('favorites')->where('user_id', $user->id)->where('business_id', $model->id)->exists() : null;

        return (new BusinessResource($model))->withFavorited($favorited);
    }

    public function reviews(Request $request, string $business): AnonymousResourceCollection
    {
        $model = $this->findPublishedBusiness($business);
        $perPage = max(1, min(50, $request->integer('per_page', 10)));

        return ReviewResource::collection(
            $model->approvedReviews()->with('user:id,name')->latest()->paginate($perPage)
        )->additional(['meta' => [
            'rating' => [
                'average' => $model->reviews_count > 0 ? round((float) $model->rating_avg, 2) : null,
                'count' => (int) $model->reviews_count,
            ],
        ]]);
    }

    public function report(Request $request, string $business): JsonResponse
    {
        $model = $this->findPublishedBusiness($business);
        $user = auth('sanctum')->user();

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1500'],
            'reporter_email' => ['nullable', 'email:rfc', 'max:191'],
        ]);

        $report = new ListingReport([
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'reporter_email' => $user?->email ?? ($data['reporter_email'] ?? null),
        ]);
        $report->business_id = $model->id;
        $report->user_id = $user?->id;
        $report->ip_hash = ip_hash();
        $report->status = 'open';
        $report->save();

        Notification::send(User::permission('reports.handle')->get(), new StaffAlert('report',
            __('notifications.staff_report_body', ['business' => $model->t('name')]), 'admin.reports.index'));

        return response()->json(['message' => __('business.report_sent')], 201);
    }

    /** Contact-interaction analytics from the app (call, whatsapp, website, directions, share). */
    public function event(Request $request, string $business, AnalyticsRecorder $analytics): Response
    {
        $model = $this->findPublishedBusiness($business);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, BusinessEventType::trackable()))],
        ]);
        $analytics->recordInteraction($model, BusinessEventType::from($data['type']), 'api');

        return response()->noContent();
    }
}
