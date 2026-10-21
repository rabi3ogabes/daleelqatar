<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Business;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function create(User $user, Business $business): bool
    {
        return $user->hasVerifiedEmail() && ! $user->isSuspended() && ! $business->isOwnedBy($user);
    }

    public function reply(User $user, Review $review): bool
    {
        return $review->business?->isOwnedBy($user) ?? false;
    }

    public function moderate(User $user, Review $review): bool
    {
        return $user->can('reviews.moderate');
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->can('reviews.moderate');
    }
}
