<?php

namespace App\ValueObjects;

use App\Enums\PlanTrigger;
use Carbon\CarbonImmutable;

class PlanSchedule
{
    public function __construct(
        public readonly PlanTrigger $trigger,
        public readonly ?int $intervalDays,
        public readonly ?string $intervalHours,
        public readonly ?CarbonImmutable $lastCompletionDate,
        public readonly ?string $lastCompletionHours,
        public readonly bool $hasHourMeter,
    ) {}
}
