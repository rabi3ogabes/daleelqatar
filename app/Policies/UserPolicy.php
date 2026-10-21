<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Prevents privilege escalation: staff can only manage accounts ranked below
 * themselves, and nobody can change their own roles or suspend themselves.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ($actor->is($target) || $actor->roleRank() > $target->roleRank());
    }

    public function suspend(User $actor, User $target): bool
    {
        return $actor->can('users.suspend') && ! $actor->is($target) && $actor->roleRank() > $target->roleRank();
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ! $actor->is($target) && $actor->roleRank() > $target->roleRank();
    }

    public function sendPasswordReset(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ($actor->roleRank() > $target->roleRank());
    }
}
