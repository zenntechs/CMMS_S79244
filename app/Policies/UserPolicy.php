<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may list the application accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::ChiefEngineer;
    }

    /**
     * Determine whether the user may inspect another account.
     */
    public function view(User $user, User $target): bool
    {
        return $user->role === UserRole::ChiefEngineer;
    }
}
