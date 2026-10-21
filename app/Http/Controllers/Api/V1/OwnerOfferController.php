<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Services\MediaService;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OwnerOfferController extends Controller
{
    use ResolvesApiModels;

    public function __construct(private readonly OfferService $offers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $offers = Offer::query()->whereIn('business_id', $request->user()->businesses()->select('id'))
            ->with('business')->latest()->paginate(15);
        $offers->getCollection()->transform(fn ($o) => (new OfferResource($o))->asOwner());

        return OfferResource::collection($offers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $business = $this->findBusiness($data['business_id']);
        $this->authorize('manageOffers', $business);

        $offer = $this->offers->create($business, $request->user(), $this->payload($data), $request->file('image'));

        return (new OfferResource($offer->load('business')))->asOwner()->response()->setStatusCode(201);
    }

    public function show(string $offer): OfferResource
    {
        $model = $this->findOffer($offer);
        $this->authorize('update', $model);

        return (new OfferResource($model->load('business')))->asOwner();
    }

    public function update(Request $request, string $offer): OfferResource
    {
        $model = $this->findOffer($offer);
        $this->authorize('update', $model);
        $data = $this->validated($request, false);

        $model = $this->offers->update($model, $request->user(), $this->payload($data), $request->file('image'));

        return (new OfferResource($model->load('business')))->asOwner();
    }

    public function destroy(Request $request, string $offer): Response
    {
        $model = $this->findOffer($offer);
        $this->authorize('delete', $model);
        $this->offers->delete($model, $request->user());

        return response()->noContent();
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'business_id' => [$creating ? 'required' : 'prohibited', 'string'],
            'title_ar' => [$req, 'string', 'max:160'],
            'title_en' => [$req, 'string', 'max:160'],
            'description_ar' => ['nullable', 'string', 'max:3000'],
            'description_en' => ['nullable', 'string', 'max:3000'],
            'discount_label_ar' => ['nullable', 'string', 'max:40'],
            'discount_label_en' => ['nullable', 'string', 'max:40'],
            'terms_ar' => ['nullable', 'string', 'max:3000'],
            'terms_en' => ['nullable', 'string', 'max:3000'],
            'starts_at' => [$req, 'date'],
            'ends_at' => [$req, 'date', 'after:starts_at', 'after:now'],
            'is_active' => ['sometimes', 'boolean'],
            'save_as_draft' => ['sometimes', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120)],
        ]);
    }

    private function payload(array $data): array
    {
        return collect($data)->except(['business_id', 'image'])->all();
    }
}
