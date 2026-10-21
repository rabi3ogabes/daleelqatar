<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public bool $withStatus = false;

    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => $this->uuid,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'locale' => $this->locale,
            'author' => ['name' => $this->authorDisplayName()],
            'owner_reply' => $this->owner_reply,
            'owner_replied_at' => $this->owner_replied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ], $this->withStatus ? [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->uuid, 'slug' => $this->business->slug, 'name' => $this->business->t('name'),
            ] : null),
        ] : []);
    }

    public function withStatus(): static
    {
        $this->withStatus = true;

        return $this;
    }
}
