<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case InService = 'in_service';
    case Standby = 'standby';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::InService => 'In service',
            self::Standby => 'Standby',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
