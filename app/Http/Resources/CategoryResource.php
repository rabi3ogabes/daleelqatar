<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @var array<int,int> published business counts keyed by category id */
    public static array $counts = [];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'description' => $this->t('description'),
            'icon' => $this->icon,
            'image_url' => $this->imageUrl(),
            'parent_id' => $this->parent_id,
            'is_featured' => (bool) $this->is_featured,
            'businesses_count' => static::$counts[$this->id] ?? 0,
            'children' => self::collection($this->whenLoaded('children')),
            'translations' => [
                'name' => $this->translations('name'),
                'description' => $this->translations('description'),
            ],
        ];
    }
}
