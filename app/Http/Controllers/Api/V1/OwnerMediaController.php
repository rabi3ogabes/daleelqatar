<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\OwnerBusinessResource;
use App\Models\Business;
use App\Services\BusinessManager;
use App\Services\MediaService;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Logo, cover, gallery and verification documents (multipart/form-data). */
class OwnerMediaController extends Controller
{
    use ResolvesApiModels;

    public function __construct(private readonly BusinessManager $manager) {}

    public function logo(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->editable($business);
        $request->validate(['logo' => $this->imageRules()]);
        $this->manager->setLogo($model, $request->file('logo'));

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function cover(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->editable($business);
        $request->validate(['cover' => $this->imageRules()]);
        $this->manager->setCover($model, $request->file('cover'));

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function destroyLogo(string $business): OwnerBusinessResource
    {
        $model = $this->editable($business);
        $this->manager->removeLogo($model);

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function destroyCover(string $business): OwnerBusinessResource
    {
        $model = $this->editable($business);
        $this->manager->removeCover($model);

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function gallery(Request $request, string $business): JsonResponse
    {
        $model = $this->editable($business);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.(int) setting('uploads.max_gallery_images', 12)],
            'images.*' => $this->imageRules(),
        ]);
        $added = $this->manager->addGalleryImages($model, $request->file('images'));

        return (new OwnerBusinessResource($this->loaded($model)))->additional(['meta' => ['added' => $added]])
            ->response()->setStatusCode(201);
    }

    public function destroyImage(string $business, string $image): Response
    {
        $model = $this->editable($business);
        $img = $model->images()->whereKey((int) $image)->firstOrFail();
        $this->manager->deleteImage($img);

        return response()->noContent();
    }

    public function reorderGallery(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->editable($business);
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $this->manager->reorderImages($model, $data['order']);

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function verification(Request $request, string $business, VerificationService $verification): JsonResponse
    {
        $model = $this->findBusiness($business);
        $this->authorize('requestVerification', $model);

        $data = $request->validate([
            'commercial_registration' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['required', 'file', 'max:'.(int) setting('uploads.max_document_kb', 8192), 'mimetypes:'.implode(',', MediaService::DOCUMENT_MIMES)],
        ]);

        $req = $verification->request($model, $request->user(), collect($data)->except('document')->all(), $request->file('document'));

        return response()->json(['data' => [
            'id' => $req->uuid,
            'status' => $req->status->value,
            'status_label' => $req->status->label(),
            'created_at' => $req->created_at?->toIso8601String(),
        ], 'message' => __('api_v1.verification_submitted')], 201);
    }

    private function editable(string $key): Business
    {
        $model = $this->findBusiness($key);
        $this->authorize('update', $model);

        return $model;
    }

    private function loaded(Business $business): Business
    {
        return $business->refresh()->load(['category', 'categories', 'municipality', 'area', 'images', 'services', 'openingHours', 'socialLinks', 'activeOffers']);
    }

    private function imageRules(): array
    {
        return ['required', 'image', 'mimetypes:'.implode(',', MediaService::IMAGE_MIMES), 'max:'.(int) setting('uploads.max_image_kb', 5120), 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'];
    }
}
