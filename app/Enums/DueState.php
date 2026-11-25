<?php

namespace App\Enums;

enum DueState: string
{
    case NotDue = 'not_due';
    case Due = 'due';
    case Overdue = 'overdue';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::NotDue => 'Not due',
            self::Due => 'Due',
            self::Overdue => 'Overdue',
            self::Incomplete => 'Incomplete',
        };
    }
}
