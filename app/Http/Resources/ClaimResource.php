<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BusinessClaimRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessClaimRequest */
class ClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->uuid, 'slug' => $this->business->slug, 'name' => $this->business->t('name'),
            ]),
            'claimant_name' => $this->claimant_name,
            'position' => $this->position,
            'has_proof' => $this->proof_path !== null,
            'decision_note' => $this->decision_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
