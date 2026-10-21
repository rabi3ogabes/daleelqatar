<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfferController extends Controller
{
    use ResolvesApiModels;

    /** Live offers only: approved, enabled, running now and attached to a published listing. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(48, $request->integer('per_page', 12)));
        $query = Offer::query()->live()->with('business:id,uuid,name_ar,name_en,slug')
            ->orderByDesc('is_featured')->orderBy('ends_at');

        if ($request->filled('business')) {
            $business = $this->findPublishedBusiness((string) $request->query('business'));
            $query->where('business_id', $business->id);
        }

        return OfferResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $offer, AnalyticsRecorder $analytics): OfferResource
    {
        $model = $this->findOffer($offer);
        $model->load('business');
        abort_unless($model->isLive() && $model->business?->isPublished(), 404);

        $analytics->recordOfferView($model, 'api');

        return new OfferResource($model);
    }
}
