<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isChiefEngineer() || $user->isViewer();
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $user->isChiefEngineer() || $user->isViewer();
    }
}
