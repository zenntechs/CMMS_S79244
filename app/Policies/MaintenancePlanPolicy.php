<?php

namespace App\Policies;

use App\Models\MaintenancePlan;
use App\Models\User;

class MaintenancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenancePlan $plan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isChiefEngineer();
    }

    public function update(User $user, MaintenancePlan $plan): bool
    {
        return $user->isChiefEngineer();
    }

    public function activate(User $user, MaintenancePlan $plan): bool
    {
        return $user->isChiefEngineer();
    }
}
