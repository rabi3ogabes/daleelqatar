<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Offer */
class OfferResource extends JsonResource
{
    public bool $ownerView = false;

    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'description' => $this->t('description'),
            'discount_label' => $this->t('discount_label'),
            'terms' => $this->t('terms'),
            'image_url' => $this->imageUrl(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_live' => $this->isLive(),
            'is_featured' => (bool) $this->is_featured,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->uuid,
                'slug' => $this->business->slug,
                'name' => $this->business->t('name'),
            ] : null),
            'translations' => [
                'title' => $this->translations('title'),
                'description' => $this->translations('description'),
                'discount_label' => $this->translations('discount_label'),
                'terms' => $this->translations('terms'),
            ],
        ], $this->ownerView ? [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => (bool) $this->is_active,
            'rejection_reason' => $this->rejection_reason,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ] : []);
    }

    public function asOwner(): static
    {
        $this->ownerView = true;

        return $this;
    }
}
