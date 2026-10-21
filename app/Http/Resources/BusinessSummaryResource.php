<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact listing representation for lists and cards. Never exposes owner
 * identity, moderation data or private documents.
 *
 * @mixin Business
 */
class BusinessSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstImage = $this->relationLoaded('images') ? $this->images->first() : null;

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'tagline' => $this->t('tagline'),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->t('name'),
                'icon' => $this->category->icon,
            ]),
            'location' => [
                'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality ? ['id' => $this->municipality->id, 'slug' => $this->municipality->slug, 'name' => $this->municipality->t('name')] : null),
                'area' => $this->whenLoaded('area', fn () => $this->area ? ['id' => $this->area->id, 'slug' => $this->area->slug, 'name' => $this->area->t('name')] : null),
                'label' => $this->relationLoaded('municipality') && $this->relationLoaded('area') ? $this->locationLabel() : null,
            ],
            'logo_url' => $this->logoUrl(),
            'cover_url' => $this->coverUrl(),
            'image_url' => $this->coverUrl() ?? $firstImage?->url() ?? $this->logoUrl(),
            'rating' => [
                'average' => $this->reviews_count > 0 ? round((float) $this->rating_avg, 2) : null,
                'count' => (int) $this->reviews_count,
            ],
            'is_verified' => (bool) $this->is_verified,
            'is_featured' => $this->isFeaturedNow(),
            'is_sample' => (bool) $this->is_demo,
            'is_open_now' => $this->relationLoaded('openingHours') ? $this->isOpenNow() : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'web_url' => $this->isPublished() ? route('business.show', $this->slug) : null,
        ];
    }
}
