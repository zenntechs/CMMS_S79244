<?php

namespace App\Enums;

enum UserRole: string
{
    case ChiefEngineer = 'chief_engineer';
    case Technician = 'technician';
    case Viewer = 'viewer';

    /**
     * Human-readable label for display in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChiefEngineer => 'Chief Engineer',
            self::Technician => 'Technician',
            self::Viewer => 'Viewer',
        };
    }
}
