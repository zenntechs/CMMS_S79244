<?php

namespace App\Policies;

use App\Models\SpareItem;
use App\Models\User;

class SpareItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SpareItem $spareItem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isChiefEngineer();
    }

    public function update(User $user, SpareItem $spareItem): bool
    {
        return $user->isChiefEngineer();
    }

    public function receive(User $user, SpareItem $spareItem): bool
    {
        return $user->isChiefEngineer();
    }

    public function adjust(User $user, SpareItem $spareItem): bool
    {
        return $user->isChiefEngineer();
    }
}
