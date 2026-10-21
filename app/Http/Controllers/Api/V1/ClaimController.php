<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClaimResource;
use App\Services\ClaimService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClaimController extends Controller
{
    use ResolvesApiModels;

    public function index(Request $request): AnonymousResourceCollection
    {
        return ClaimResource::collection($request->user()->claimRequests()->with('business')->latest()->paginate(15));
    }

    public function store(Request $request, string $business, ClaimService $claims): JsonResponse
    {
        $model = $this->findPublishedBusiness($business);

        $data = $request->validate([
            'claimant_name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\s\-()]{7,32}$/'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'message' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'max:'.(int) setting('uploads.max_document_kb', 8192), 'mimetypes:'.implode(',', MediaService::DOCUMENT_MIMES)],
            'terms' => ['accepted'],
        ]);

        $claim = $claims->request($model, $request->user(), collect($data)->except(['proof', 'terms'])->all(), $request->file('proof'));

        return (new ClaimResource($claim->load('business')))->additional(['message' => __('claims.submitted')])
            ->response()->setStatusCode(201);
    }
}
