<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Area */
class AreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'municipality_id' => $this->municipality_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_featured' => (bool) $this->is_featured,
            'translations' => ['name' => $this->translations('name')],
        ];
    }
}
