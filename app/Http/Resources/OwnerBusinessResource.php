<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use App\Services\ListingWorkflow;
use Illuminate\Http\Request;

/**
 * Owner-scoped listing: the public profile plus moderation state.
 *
 * @mixin Business
 */
class OwnerBusinessResource extends BusinessResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'missing_requirements' => app(ListingWorkflow::class)->missingRequirements($this->resource),
            'can_submit' => in_array($this->status->value, ['draft', 'rejected'], true),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'views_count' => (int) $this->views_count,
            'category_id' => $this->category_id,
            'municipality_id' => $this->municipality_id,
            'area_id' => $this->area_id,
            'meta' => [
                'title' => $this->translations('meta_title'),
                'description' => $this->translations('meta_description'),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
