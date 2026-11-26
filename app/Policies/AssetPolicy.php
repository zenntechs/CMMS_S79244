<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isChiefEngineer();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->isChiefEngineer();
    }

    public function recordReading(User $user, Asset $asset): bool
    {
        return $user->isChiefEngineer() || $user->isTechnician();
    }
}
