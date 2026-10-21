<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;

/**
 * Ownership is checked on every owner action; staff abilities come from permissions.
 * (Super administrators are granted everything by Gate::before.)
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('businesses.view');
    }

    public function view(?User $user, Business $business): bool
    {
        if ($business->isPublished()) {
            return true;
        }

        return $user !== null && ($business->isOwnedBy($user) || $user->can('businesses.view'));
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && ! $user->isSuspended();
    }

    public function update(User $user, Business $business): bool
    {
        if ($user->can('businesses.update')) {
            return true;
        }

        return $business->isOwnedBy($user)
            && ! in_array($business->status, [BusinessStatus::Suspended, BusinessStatus::Archived], true)
            && ! $user->isSuspended();
    }

    public function submit(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user) && in_array($business->status, [BusinessStatus::Draft, BusinessStatus::Rejected], true);
    }

    public function archive(User $user, Business $business): bool
    {
        return ($business->isOwnedBy($user) || $user->can('businesses.moderate')) && $business->status !== BusinessStatus::Archived;
    }

    public function unarchive(User $user, Business $business): bool
    {
        return ($business->isOwnedBy($user) || $user->can('businesses.moderate')) && $business->status === BusinessStatus::Archived;
    }

    public function delete(User $user, Business $business): bool
    {
        if ($user->can('businesses.delete')) {
            return true;
        }

        return $business->isOwnedBy($user) && in_array($business->status, [BusinessStatus::Draft, BusinessStatus::Rejected, BusinessStatus::Archived], true);
    }

    public function moderate(User $user, Business $business): bool
    {
        return $user->can('businesses.moderate');
    }

    public function feature(User $user, Business $business): bool
    {
        return $user->can('businesses.feature');
    }

    public function verify(User $user, Business $business): bool
    {
        return $user->can('businesses.verify');
    }

    public function requestVerification(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user) && $business->status === BusinessStatus::Approved && ! $business->is_verified;
    }

    public function manageOffers(User $user, Business $business): bool
    {
        return ($business->isOwnedBy($user) && $business->status === BusinessStatus::Approved) || $user->can('offers.manage');
    }

    public function viewStats(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user) || $user->can('analytics.view');
    }
}
