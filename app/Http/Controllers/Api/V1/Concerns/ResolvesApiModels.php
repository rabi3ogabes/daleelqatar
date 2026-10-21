<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Business;
use App\Models\Offer;
use App\Models\Review;
use Illuminate\Support\Str;

/**
 * API identifiers: businesses accept a UUID or a slug; offers and reviews use UUIDs.
 * Lookups are explicit (no global route binders) so web route bindings stay untouched.
 */
trait ResolvesApiModels
{
    protected function findBusiness(string $key): Business
    {
        return Business::query()
            ->when(Str::isUuid($key), fn ($q) => $q->where('uuid', $key), fn ($q) => $q->where('slug', $key))
            ->firstOrFail();
    }

    /** A business visible to the current requester (published, or owned / staff). */
    protected function findVisibleBusiness(string $key): Business
    {
        $business = $this->findBusiness($key);
        $user = auth('sanctum')->user();

        abort_unless($business->isPublished() || ($user && $user->can('view', $business)), 404);

        return $business;
    }

    protected function findPublishedBusiness(string $key): Business
    {
        $business = $this->findBusiness($key);
        abort_unless($business->isPublished(), 404);

        return $business;
    }

    protected function findOffer(string $key): Offer
    {
        return Offer::query()
            ->when(Str::isUuid($key), fn ($q) => $q->where('uuid', $key), fn ($q) => $q->where('slug', $key))
            ->firstOrFail();
    }

    protected function findReview(string $uuid): Review
    {
        abort_unless(Str::isUuid($uuid), 404);

        return Review::query()->where('uuid', $uuid)->firstOrFail();
    }
}
