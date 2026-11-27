<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if ($user->isChiefEngineer() || $user->isViewer()) {
            return true;
        }

        if ($user->isTechnician()) {
            return $workOrder->assigned_to === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isChiefEngineer();
    }
}
