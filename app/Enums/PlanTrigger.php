<?php

namespace App\Enums;

enum PlanTrigger: string
{
    case Calendar = 'calendar';
    case Hours = 'hours';
    case Dual = 'dual';

    public function label(): string
    {
        return match ($this) {
            self::Calendar => 'Calendar',
            self::Hours => 'Running hours',
            self::Dual => 'Dual',
        };
    }

    public function usesCalendar(): bool
    {
        return $this === self::Calendar || $this === self::Dual;
    }

    public function usesHours(): bool
    {
        return $this === self::Hours || $this === self::Dual;
    }
}
