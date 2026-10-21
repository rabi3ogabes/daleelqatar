<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function update(User $user, Offer $offer): bool
    {
        return $user->can('offers.manage') || $offer->business?->isOwnedBy($user);
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $this->update($user, $offer);
    }

    public function moderate(User $user, Offer $offer): bool
    {
        return $user->can('offers.moderate');
    }
}
