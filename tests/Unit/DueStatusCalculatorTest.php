<?php

namespace Tests\Unit;

use App\Enums\DueState;
use App\Enums\DueTrigger;
use App\Enums\PlanTrigger;
use App\Services\DueStatusCalculator;
use App\ValueObjects\PlanSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DueStatusCalculatorTest extends TestCase
{
    private DueStatusCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DueStatusCalculator;
    }

    private function schedule(PlanTrigger $trigger, ?int $days = null, ?string $hours = null, ?string $lastDate = null, ?string $lastHours = null, bool $meter = true): PlanSchedule
    {
        return new PlanSchedule(
            trigger: $trigger,
            intervalDays: $days,
            intervalHours: $hours,
            lastCompletionDate: $lastDate ? CarbonImmutable::parse($lastDate, 'Asia/Kuala_Lumpur') : null,
            lastCompletionHours: $lastHours,
            hasHourMeter: $meter,
        );
    }

    public function test_calendar_below_equal_above(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01');
        $this->assertSame(DueState::NotDue, $this->calc->calculate($s, null, CarbonImmutable::parse('2026-11-30', 'Asia/Kuala_Lumpur'))->state);
        $this->assertSame(DueState::Due, $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-01', 'Asia/Kuala_Lumpur'))->state);
        $this->assertSame(DueState::Overdue, $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'))->state);
    }

    public function test_calendar_trigger_cause(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-01', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueTrigger::Calendar, $r->trigger);
        $this->assertSame('2026-12-01', $r->dueDate->toDateString());
    }

    public function test_hours_below_equal_above(): void
    {
        $s = $this->schedule(PlanTrigger::Hours, null, '250.00', null, '1200.00');
        $this->assertSame(DueState::NotDue, $this->calc->calculate($s, '1449.99', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'))->state);
        $this->assertSame(DueState::Due, $this->calc->calculate($s, '1450.00', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'))->state);
        $this->assertSame(DueState::Overdue, $this->calc->calculate($s, '1450.01', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'))->state);
    }

    public function test_missing_calendar_baseline_is_incomplete(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, null);
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Incomplete, $r->state);
        $this->assertSame(DueState::Incomplete, $r->calendarState);
    }

    public function test_missing_hours_reading_is_incomplete(): void
    {
        $s = $this->schedule(PlanTrigger::Hours, null, '250.00', null, '1200.00');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Incomplete, $r->state);
        $this->assertTrue($r->hoursCheckIncomplete);
    }

    public function test_no_hour_meter_never_incomplete_for_hours(): void
    {
        // A plan that does not use hours has a not_due hours state, never incomplete
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01', null, false);
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Overdue, $r->state);
        $this->assertSame(DueState::NotDue, $r->hoursState);
    }

    public function test_dual_or_overdue_via_hours(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', '1200.00');
        $r = $this->calc->calculate($s, '1450.00', CarbonImmutable::parse('2026-11-20', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Due, $r->state);
        $this->assertSame(DueTrigger::Hours, $r->trigger);
    }

    public function test_dual_overdue_via_calendar(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', '1200.00');
        $r = $this->calc->calculate($s, '1200.00', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Overdue, $r->state);
        $this->assertSame(DueTrigger::Calendar, $r->trigger);
    }

    public function test_dual_both_overdue(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', '1200.00');
        $r = $this->calc->calculate($s, '1451.00', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Overdue, $r->state);
        $this->assertSame(DueTrigger::Both, $r->trigger);
    }

    public function test_dual_one_missing_calendar_incomplete_preserves_hours_due(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', null, '1200.00');
        $r = $this->calc->calculate($s, '1450.00', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Due, $r->state);
        $this->assertSame(DueTrigger::Hours, $r->trigger);
        $this->assertSame(DueState::Incomplete, $r->calendarState);
    }

    public function test_dual_both_missing_is_not_due_with_incomplete_warning(): void
    {
        // Protected spec §D.2: dual plans with no reached threshold stay "Not due"
        // but must expose the incomplete checks for a warning.
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', null, null);
        $r = $this->calc->calculate($s, '1000.00', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::NotDue, $r->state);
        $this->assertSame(DueTrigger::None, $r->trigger);
        $this->assertTrue($r->hasIncompleteCheck());
        $this->assertSame(DueState::Incomplete, $r->calendarState);
        $this->assertSame(DueState::Incomplete, $r->hoursState);
    }

    public function test_dual_one_overdue_and_other_incomplete_is_overdue(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', null);
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Overdue, $r->state);
        $this->assertSame(DueTrigger::Calendar, $r->trigger);
        $this->assertSame(DueState::Incomplete, $r->hoursState);
    }

    public function test_dual_trigger_covers_all_nine_combinations(): void
    {
        $cases = [
            ['2026-11-20', '1300.00', DueState::NotDue, DueTrigger::None],
            ['2026-11-20', '1450.00', DueState::Due, DueTrigger::Hours],
            ['2026-11-20', '1500.00', DueState::Overdue, DueTrigger::Hours],
            ['2026-12-01', '1300.00', DueState::Due, DueTrigger::Calendar],
            ['2026-12-01', '1450.00', DueState::Due, DueTrigger::Both],
            ['2026-12-01', '1500.00', DueState::Overdue, DueTrigger::Both],
            ['2026-12-02', '1300.00', DueState::Overdue, DueTrigger::Calendar],
            ['2026-12-02', '1450.00', DueState::Overdue, DueTrigger::Both],
            ['2026-12-02', '1500.00', DueState::Overdue, DueTrigger::Both],
        ];

        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', '1200.00');

        foreach ($cases as [$date, $hours, $expectedState, $expectedTrigger]) {
            $r = $this->calc->calculate($s, $hours, CarbonImmutable::parse($date, 'Asia/Kuala_Lumpur'));
            $this->assertSame($expectedState, $r->state, "state for {$date} at {$hours} h");
            $this->assertSame($expectedTrigger, $r->trigger, "cause for {$date} at {$hours} h");
        }
    }

    public function test_remaining_days_and_hours_semantics(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', '2026-11-01', '1200.00');

        $future = $this->calc->calculate($s, '1300.00', CarbonImmutable::parse('2026-11-20', 'Asia/Kuala_Lumpur'));
        $this->assertSame(11, $future->remainingDays());
        $this->assertSame('150.00', $future->remainingHours());

        $onDueDate = $this->calc->calculate($s, '1450.00', CarbonImmutable::parse('2026-12-01', 'Asia/Kuala_Lumpur'));
        $this->assertSame(0, $onDueDate->remainingDays());
        $this->assertSame('0.00', $onDueDate->remainingHours());

        $overdue = $this->calc->calculate($s, '1500.50', CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(-1, $overdue->remainingDays());
        $this->assertSame('-50.50', $overdue->remainingHours());
    }

    public function test_remaining_values_are_null_when_threshold_is_unknown(): void
    {
        $s = $this->schedule(PlanTrigger::Dual, 30, '250.00', null, null);
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertNull($r->remainingDays());
        $this->assertNull($r->remainingHours());
    }

    public function test_future_baseline_is_not_due(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2027-01-01');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::NotDue, $r->state);
        $this->assertSame('2027-01-31', $r->dueDate->toDateString());
    }

    public function test_month_end_and_year_end_transitions(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 60, null, '2026-12-31');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2027-03-01', 'Asia/Kuala_Lumpur'));
        $this->assertSame('2027-03-01', $r->dueDate->toDateString());
        $this->assertSame(DueState::Due, $r->state);

        $s2 = $this->schedule(PlanTrigger::Calendar, 31, null, '2027-01-31');
        $r2 = $this->calc->calculate($s2, null, CarbonImmutable::parse('2027-03-03', 'Asia/Kuala_Lumpur'));
        $this->assertSame('2027-03-03', $r2->dueDate->toDateString());
    }

    public function test_leap_year_transition(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 29, null, '2028-02-01');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2028-03-01', 'Asia/Kuala_Lumpur'));
        $this->assertSame('2028-03-01', $r->dueDate->toDateString());
        $this->assertSame(DueState::Due, $r->state);
    }

    public function test_does_not_mutate_inputs(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01');
        $dateBefore = $s->lastCompletionDate->toDateString();
        $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-02', 'Asia/Kuala_Lumpur'));
        $this->assertSame($dateBefore, $s->lastCompletionDate->toDateString());
        $this->assertSame(30, $s->intervalDays);
    }

    public function test_timezone_equality(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01');
        $r = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-01 00:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Due, $r->state);
    }

    public function test_midnight_boundary_at_kuala_lumpur(): void
    {
        $s = $this->schedule(PlanTrigger::Calendar, 30, null, '2026-11-01');

        // 23:59 on 30 Nov is still before the due date.
        $before = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-11-30 23:59:59', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::NotDue, $before->state);

        // 00:00 on 1 Dec is due.
        $at = $this->calc->calculate($s, null, CarbonImmutable::parse('2026-12-01 00:00:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame(DueState::Due, $at->state);
    }
}
