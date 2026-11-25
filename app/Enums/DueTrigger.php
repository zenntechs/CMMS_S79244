<?php

namespace App\Enums;

enum DueTrigger: string
{
    case None = 'none';
    case Calendar = 'calendar';
    case Hours = 'hours';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Calendar => 'Calendar',
            self::Hours => 'Running hours',
            self::Both => 'Both',
        };
    }
}
