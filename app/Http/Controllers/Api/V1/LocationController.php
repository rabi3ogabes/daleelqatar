<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MunicipalityResource;
use App\Models\Municipality;
use App\Support\Directory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    /** Qatar's municipalities with their active areas. */
    public function index(): AnonymousResourceCollection
    {
        MunicipalityResource::$counts = Directory::municipalityCounts();

        return MunicipalityResource::collection(
            Municipality::query()->active()->ordered()->with(['areas' => fn ($q) => $q->active()])->get()
        );
    }

    public function show(string $slug): MunicipalityResource
    {
        MunicipalityResource::$counts = Directory::municipalityCounts();

        return new MunicipalityResource(
            Municipality::query()->active()->where('slug', $slug)->with(['areas' => fn ($q) => $q->active()])->firstOrFail()
        );
    }
}
