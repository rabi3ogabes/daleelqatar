<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSummaryResource;
use App\Models\Business;
use App\Support\Directory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    use ResolvesApiModels;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(48, $request->integer('per_page', 12)));

        return BusinessSummaryResource::collection(
            $request->user()->favorites()->published()->with(Directory::cardRelations())
                ->orderByPivot('created_at', 'desc')->paginate($perPage)
        );
    }

    /** Idempotent: adding an existing favourite is a no-op. */
    public function store(Request $request, string $business): JsonResponse
    {
        $model = $this->findPublishedBusiness($business);
        $request->user()->favorites()->syncWithoutDetaching([$model->id]);
        $this->refreshCount($model);

        return response()->json(['data' => ['business_id' => $model->uuid, 'favorited' => true], 'message' => __('business.favorite_added')]);
    }

    /** Idempotent: removing a missing favourite is a no-op. */
    public function destroy(Request $request, string $business): JsonResponse
    {
        $model = $this->findBusiness($business);
        $request->user()->favorites()->detach($model->id);
        $this->refreshCount($model);

        return response()->json(['data' => ['business_id' => $model->uuid, 'favorited' => false], 'message' => __('business.favorite_removed')]);
    }

    private function refreshCount(Business $business): void
    {
        $business->forceFill(['favorites_count' => DB::table('favorites')->where('business_id', $business->id)->count()])->saveQuietly();
    }
}
