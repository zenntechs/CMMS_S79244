<?php

namespace App\Enums;

enum WorkOrderState: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Submitted => 'Submitted',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::Submitted], true);
    }
}
