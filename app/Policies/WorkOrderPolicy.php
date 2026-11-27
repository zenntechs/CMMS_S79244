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

    public function start(User $user, WorkOrder $workOrder): bool
    {
        return $this->mayPerformWork($user, $workOrder);
    }

    public function submit(User $user, WorkOrder $workOrder): bool
    {
        return $this->mayPerformWork($user, $workOrder);
    }

    public function returnToTechnician(User $user, WorkOrder $workOrder): bool
    {
        return $user->isChiefEngineer();
    }

    private function mayPerformWork(User $user, WorkOrder $workOrder): bool
    {
        if ($user->isChiefEngineer()) {
            return true;
        }

        return $user->isTechnician() && $workOrder->assigned_to === $user->id;
    }
}
