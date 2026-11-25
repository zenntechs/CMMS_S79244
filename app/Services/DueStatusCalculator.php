<?php

namespace App\Services;

use App\Enums\DueState;
use App\Enums\DueTrigger;
use App\Enums\PlanTrigger;
use App\Models\MaintenancePlan;
use App\ValueObjects\DueStatus;
use App\ValueObjects\PlanSchedule;
use Carbon\CarbonImmutable;

class DueStatusCalculator
{
    public function calculate(PlanSchedule $plan, ?string $currentHours, CarbonImmutable $today): DueStatus
    {
        $asOf = $today->startOfDay();

        // Calendar
        $calendarState = DueState::NotDue;
        $dueDate = null;
        if ($plan->trigger->usesCalendar()) {
            if ($plan->lastCompletionDate === null || $plan->intervalDays === null) {
                $calendarState = DueState::Incomplete;
            } else {
                $dueDate = $plan->lastCompletionDate->addDays($plan->intervalDays);
                $calendarState = $this->compareDate($asOf, $dueDate);
            }
        }

        // Hours
        $hoursState = DueState::NotDue;
        $hoursDueAt = null;
        $hoursIncomplete = false;
        if ($plan->trigger->usesHours()) {
            if (! $plan->hasHourMeter || $plan->lastCompletionHours === null || $plan->intervalHours === null || $currentHours === null) {
                $hoursState = DueState::Incomplete;
                $hoursIncomplete = true;
            } else {
                $hoursDueAt = bcadd($plan->lastCompletionHours, $plan->intervalHours, 2);
                $hoursState = $this->compareHours($currentHours, $hoursDueAt);
            }
        }

        // Overall state precedence: Overdue > Due > (dual: Not due) > Incomplete > Not due.
        $overdueSides = [];
        $reachedSides = [];

        if ($plan->trigger->usesCalendar()) {
            if ($calendarState === DueState::Overdue) {
                $overdueSides[] = 'calendar';
            }
            if (in_array($calendarState, [DueState::Due, DueState::Overdue], true)) {
                $reachedSides[] = 'calendar';
            }
        }

        if ($plan->trigger->usesHours()) {
            if ($hoursState === DueState::Overdue) {
                $overdueSides[] = 'hours';
            }
            if (in_array($hoursState, [DueState::Due, DueState::Overdue], true)) {
                $reachedSides[] = 'hours';
            }
        }

        // Protected spec §D.2: the trigger cause reports every side that reached
        // its threshold (Due or Overdue), independently of state precedence.
        $trigger = $reachedSides === [] ? DueTrigger::None : $this->triggerFrom($reachedSides);

        if ($overdueSides !== []) {
            $state = DueState::Overdue;
        } elseif ($reachedSides !== []) {
            $state = DueState::Due;
        } elseif ($plan->trigger === PlanTrigger::Dual) {
            // For dual plans, a missing input on one side keeps the plan "Not due"
            // (with an incomplete warning) unless a threshold is reached.
            $state = DueState::NotDue;
        } elseif ($calendarState === DueState::Incomplete || $hoursState === DueState::Incomplete) {
            $state = DueState::Incomplete;
        } else {
            $state = DueState::NotDue;
        }

        return new DueStatus(
            state: $state,
            trigger: $trigger,
            calendarState: $calendarState,
            hoursState: $hoursState,
            dueDate: $dueDate,
            hoursDueAt: $hoursDueAt,
            hoursCheckIncomplete: $hoursIncomplete,
            asOfDate: $asOf,
            currentHours: $currentHours,
        );
    }

    public function calculateForPlan(MaintenancePlan $plan, ?string $currentHours, CarbonImmutable $today): DueStatus
    {
        $schedule = new PlanSchedule(
            trigger: $plan->trigger_type,
            intervalDays: $plan->interval_days,
            intervalHours: $plan->interval_hours !== null ? (string) $plan->interval_hours : null,
            lastCompletionDate: $plan->last_completion_date !== null
                ? CarbonImmutable::parse($plan->last_completion_date->toDateString(), 'Asia/Kuala_Lumpur')->startOfDay()
                : null,
            lastCompletionHours: $plan->last_completion_hours !== null ? (string) $plan->last_completion_hours : null,
            hasHourMeter: (bool) $plan->asset?->has_hour_meter,
        );

        return $this->calculate($schedule, $currentHours, $today);
    }

    private function compareDate(CarbonImmutable $today, CarbonImmutable $due): DueState
    {
        if ($today->lt($due)) {
            return DueState::NotDue;
        }
        if ($today->eq($due)) {
            return DueState::Due;
        }

        return DueState::Overdue;
    }

    private function compareHours(string $current, string $due): DueState
    {
        $cmp = bccomp($current, $due, 2);
        if ($cmp < 0) {
            return DueState::NotDue;
        }
        if ($cmp === 0) {
            return DueState::Due;
        }

        return DueState::Overdue;
    }

    /**
     * @param  array<int, string>  $triggers
     */
    private function triggerFrom(array $triggers): DueTrigger
    {
        $hasCalendar = in_array('calendar', $triggers, true);
        $hasHours = in_array('hours', $triggers, true);

        if ($hasCalendar && $hasHours) {
            return DueTrigger::Both;
        }
        if ($hasCalendar) {
            return DueTrigger::Calendar;
        }

        return DueTrigger::Hours;
    }
}
