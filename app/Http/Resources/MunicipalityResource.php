<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Municipality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Municipality */
class MunicipalityResource extends JsonResource
{
    /** @var array<int,int> */
    public static array $counts = [];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_featured' => (bool) $this->is_featured,
            'businesses_count' => static::$counts[$this->id] ?? 0,
            'areas' => AreaResource::collection($this->whenLoaded('areas')),
            'translations' => ['name' => $this->translations('name')],
        ];
    }
}
