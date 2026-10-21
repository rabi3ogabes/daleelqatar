<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->preferredLocale(),
            'avatar_url' => $this->avatarUrl(),
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'roles' => $this->getRoleNames()->values(),
            'is_business_owner' => $this->hasRole(Permissions::BUSINESS_OWNER),
            'notification_preferences' => array_replace(['email' => true, 'listing_updates' => true, 'reviews' => true], (array) ($this->notification_preferences ?? [])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
