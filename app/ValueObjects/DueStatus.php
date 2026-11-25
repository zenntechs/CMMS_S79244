<?php

namespace App\ValueObjects;

use App\Enums\DueState;
use App\Enums\DueTrigger;
use Carbon\CarbonImmutable;

class DueStatus
{
    public function __construct(
        public readonly DueState $state,
        public readonly DueTrigger $trigger,
        public readonly DueState $calendarState,
        public readonly DueState $hoursState,
        public readonly ?CarbonImmutable $dueDate,
        public readonly ?string $hoursDueAt,
        public readonly bool $hoursCheckIncomplete,
        public readonly CarbonImmutable $asOfDate,
        public readonly ?string $currentHours = null,
    ) {}

    public function remainingDays(): ?int
    {
        if ($this->dueDate === null) {
            return null;
        }

        return (int) $this->asOfDate->diffInDays($this->dueDate, false);
    }

    public function remainingHours(): ?string
    {
        if ($this->hoursDueAt === null || $this->currentHours === null) {
            return null;
        }

        return bcsub($this->hoursDueAt, $this->currentHours, 2);
    }

    public function hasIncompleteCheck(): bool
    {
        return $this->calendarState === DueState::Incomplete || $this->hoursState === DueState::Incomplete;
    }
}
