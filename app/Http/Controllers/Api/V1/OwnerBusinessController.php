<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequest;
use App\Http\Resources\OwnerBusinessResource;
use App\Models\Business;
use App\Services\BusinessManager;
use App\Services\ListingWorkflow;
use App\Support\Directory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Business-owner listing management. Every action is ownership-checked by BusinessPolicy.
 */
class OwnerBusinessController extends Controller
{
    use ResolvesApiModels;

    public function __construct(
        private readonly BusinessManager $manager,
        private readonly ListingWorkflow $workflow,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'string', 'in:draft,pending,approved,rejected,suspended,archived']]);

        $businesses = $request->user()->businesses()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->with(Directory::cardRelations())->latest('updated_at')->paginate(15)->withQueryString();

        return OwnerBusinessResource::collection($businesses);
    }

    public function store(BusinessRequest $request): JsonResponse
    {
        $this->authorize('create', Business::class);

        $business = $this->manager->create($request->user(), $request->businessData());
        $this->storeImages($request, $business);

        return (new OwnerBusinessResource($this->loaded($business)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->owned($request, $business);

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function update(BusinessRequest $request, string $business): OwnerBusinessResource
    {
        $model = $this->findBusiness($business);
        $this->authorize('update', $model);

        $model = $this->manager->update($model, $request->user(), $request->businessData());
        $this->storeImages($request, $model);

        return new OwnerBusinessResource($this->loaded($model));
    }

    public function destroy(Request $request, string $business): Response
    {
        $model = $this->findBusiness($business);
        $this->authorize('delete', $model);
        $this->manager->delete($model, $request->user());

        return response()->noContent();
    }

    public function submit(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->findBusiness($business);
        $this->authorize('submit', $model);
        $this->workflow->submit($model, $request->user());

        return new OwnerBusinessResource($this->loaded($model->refresh()));
    }

    public function archive(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->findBusiness($business);
        $this->authorize('archive', $model);
        $this->workflow->archive($model, $request->user());

        return new OwnerBusinessResource($this->loaded($model->refresh()));
    }

    public function unarchive(Request $request, string $business): OwnerBusinessResource
    {
        $model = $this->findBusiness($business);
        $this->authorize('unarchive', $model);
        $this->workflow->unarchive($model, $request->user());

        return new OwnerBusinessResource($this->loaded($model->refresh()));
    }

    /** Owner-only lookup: other people's listings are reported as 403, missing ones as 404. */
    protected function owned(Request $request, string $key): Business
    {
        $model = $this->findBusiness($key);
        abort_unless($model->isOwnedBy($request->user()), 403);

        return $model;
    }

    protected function loaded(Business $business): Business
    {
        return $business->load(['category', 'categories', 'municipality', 'area', 'images', 'services', 'openingHours', 'socialLinks', 'activeOffers']);
    }

    private function storeImages(Request $request, Business $business): void
    {
        if ($request->hasFile('logo')) {
            $this->manager->setLogo($business, $request->file('logo'));
        }
        if ($request->hasFile('cover')) {
            $this->manager->setCover($business, $request->file('cover'));
        }
    }
}
