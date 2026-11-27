<?php

namespace Tests\Feature;

use App\Enums\DueState;
use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\MeterReading;
use App\Models\PlanBaselineHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Asia/Kuala_Lumpur'));
    }

    private function chief(): User
    {
        return User::where('email', 'chief@cmms.test')->firstOrFail();
    }

    private function tech1(): User
    {
        return User::where('email', 'tech1@cmms.test')->firstOrFail();
    }

    private function tech2(): User
    {
        return User::where('email', 'tech2@cmms.test')->firstOrFail();
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    private function workOrder(string $reference): WorkOrder
    {
        return WorkOrder::where('reference', $reference)->firstOrFail();
    }

    private function closeUrl(WorkOrder $workOrder): string
    {
        return route('work-orders.close', $workOrder);
    }

    public function test_chief_engineer_can_close_a_submitted_hours_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0003'); // submitted, P04 hours plan, actual 1420.00
        $chief = $this->chief();
        $plan = $workOrder->plan;

        $baselineHistoryBefore = PlanBaselineHistory::count();
        $meterCountBefore = MeterReading::count();
        $meterHashesBefore = MeterReading::orderBy('id')->pluck('reading_hours')->all();

        $this->actingAs($chief)
            ->post($this->closeUrl($workOrder))
            ->assertRedirect(route('work-orders.show', $workOrder));

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Closed, $workOrder->state);
        $this->assertNotNull($workOrder->closed_at);
        $this->assertSame($chief->id, $workOrder->approved_by);
        $this->assertSame('1420.00', (string) $workOrder->actual_completion_hours);

        $plan->refresh();
        $this->assertSame('1420.00', (string) $plan->last_completion_hours);
        $this->assertSame($workOrder->id, $plan->last_completion_work_order_id);

        $this->assertSame($baselineHistoryBefore + 1, PlanBaselineHistory::count());
        $history = PlanBaselineHistory::where('work_order_id', $workOrder->id)->sole();
        $this->assertSame('1200.00', (string) $history->previous_hours);
        $this->assertSame('1420.00', (string) $history->new_hours);
        $this->assertSame($chief->id, $history->changed_by);

        $closedEvent = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Closed)->sole();
        $this->assertSame($chief->id, $closedEvent->actor_id);
        $this->assertSame(WorkOrderState::Submitted, $closedEvent->from_state);
        $this->assertSame(WorkOrderState::Closed, $closedEvent->to_state);

        $audit = AuditEvent::where('action', 'work_order.closed')
            ->where('entity_id', $workOrder->id)->sole();
        $this->assertSame($chief->id, $audit->actor_id);

        $this->assertSame($meterCountBefore, MeterReading::count());
        $this->assertSame($meterHashesBefore, MeterReading::orderBy('id')->pluck('reading_hours')->all());
    }

    public function test_closure_uses_actual_completion_date_not_approval_timestamp(): void
    {
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail(); // calendar plan, no active work order
        $tech = $this->tech1();
        $chief = $this->chief();

        $workOrder = WorkOrder::factory()->create([
            'reference' => 'TST-WO-CAL-TS',
            'plan_id' => $plan->id,
            'state' => WorkOrderState::Open,
            'assigned_to' => $tech->id,
            'created_by' => $chief->id,
        ]);

        $this->actingAs($tech)->post(route('work-orders.start', $workOrder))->assertRedirect();
        $this->actingAs($tech)->post(route('work-orders.submit', $workOrder), [
            'actual_completion_date' => '2026-11-20',
            'completion_notes' => 'Completed earlier than the approval date.',
        ])->assertRedirect();

        $this->actingAs($chief)
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $plan->refresh();
        $workOrder->refresh();

        $this->assertSame('2026-11-20', $plan->last_completion_date->toDateString());
        $this->assertSame('2026-11-25', $workOrder->closed_at->toDateString());
    }

    public function test_chief_engineer_can_close_a_submitted_dual_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0004'); // submitted, P08 dual plan
        $plan = $workOrder->plan;

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('2026-11-22', $plan->last_completion_date->toDateString());
        $this->assertSame('2050.00', (string) $plan->last_completion_hours);
        $this->assertSame($workOrder->id, $plan->last_completion_work_order_id);

        $history = PlanBaselineHistory::where('work_order_id', $workOrder->id)->sole();
        $this->assertSame('2026-10-20', $history->previous_date->toDateString());
        $this->assertSame('2026-11-22', $history->new_date->toDateString());
        $this->assertSame('2000.00', (string) $history->previous_hours);
        $this->assertSame('2050.00', (string) $history->new_hours);
    }

    public function test_chief_engineer_can_close_a_submitted_calendar_work_order(): void
    {
        // Create and submit a calendar work order on P01 (no pre-existing active work order).
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $tech = $this->tech2();
        $chief = $this->chief();

        $workOrder = WorkOrder::factory()->create([
            'reference' => 'TST-WO-CAL',
            'plan_id' => $plan->id,
            'state' => WorkOrderState::Open,
            'assigned_to' => $tech->id,
            'created_by' => $chief->id,
        ]);

        $this->actingAs($tech)->post(route('work-orders.start', $workOrder))->assertRedirect();
        $this->actingAs($tech)->post(route('work-orders.submit', $workOrder), [
            'actual_completion_date' => '2026-11-25',
            'completion_notes' => 'Calendar maintenance completed and verified.',
        ])->assertRedirect();

        $beforeDate = $plan->last_completion_date->toDateString();
        $beforeHours = $plan->last_completion_hours;

        $this->actingAs($chief)
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('2026-11-25', $plan->last_completion_date->toDateString());
        $this->assertSame($workOrder->id, $plan->last_completion_work_order_id);
        // Calendar closure must not overwrite running-hour baseline.
        $this->assertSame($beforeHours, $plan->last_completion_hours);

        $history = PlanBaselineHistory::where('work_order_id', $workOrder->id)->sole();
        $this->assertSame($beforeDate, $history->previous_date->toDateString());
        $this->assertSame('2026-11-25', $history->new_date->toDateString());
        $this->assertNull($history->previous_hours);
        $this->assertNull($history->new_hours);
    }

    public function test_closed_work_order_displays_recalculated_due_status(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $plan = $workOrder->plan->refresh()->load('asset.latestMeterReading');
        $reading = $plan->asset->latestMeterReading;
        $dueStatus = (new DueStatusCalculator)->calculateForPlan(
            $plan,
            $reading ? (string) $reading->reading_hours : null,
            CarbonImmutable::parse('2026-11-25', 'Asia/Kuala_Lumpur'),
        );

        $this->assertSame(DueState::NotDue, $dueStatus->state);
        $this->assertSame('1670.00', $dueStatus->hoursDueAt);
    }

    public function test_closed_work_order_is_immutable(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $eventsBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)->count();
        $auditsBefore = AuditEvent::where('entity_id', $workOrder->id)->count();

        $this->actingAs($this->chief())
            ->post(route('work-orders.start', $workOrder))
            ->assertSessionHasErrors('work_order');

        $this->actingAs($this->chief())
            ->post(route('work-orders.submit', $workOrder), [
                'actual_completion_date' => '2026-11-25',
                'completion_notes' => 'Attempted resubmission after closure.',
            ])
            ->assertSessionHasErrors('work_order');

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'Attempted return after closure.'])
            ->assertSessionHasErrors('work_order');

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertSessionHasErrors('work_order');

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Closed, $workOrder->state);
        $this->assertSame($eventsBefore, WorkOrderEvent::where('work_order_id', $workOrder->id)->count());
        $this->assertSame($auditsBefore, AuditEvent::where('entity_id', $workOrder->id)->count());
    }

    public function test_missing_completion_information_prevents_closure(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $workOrder->update(['actual_completion_date' => null]);

        $baselineBefore = $workOrder->plan->last_completion_hours;
        $historyBefore = PlanBaselineHistory::count();
        $eventsBefore = WorkOrderEvent::count();
        $auditsBefore = AuditEvent::count();

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertSessionHasErrors('actual_completion_date');

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Submitted, $workOrder->state);
        $this->assertSame($baselineBefore, $workOrder->plan->refresh()->last_completion_hours);
        $this->assertSame($historyBefore, PlanBaselineHistory::count());
        $this->assertSame($eventsBefore, WorkOrderEvent::count());
        $this->assertSame($auditsBefore, AuditEvent::count());
    }

    public function test_baseline_regression_prevents_closure(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $workOrder->update(['actual_completion_hours' => '1000.00']);

        $historyBefore = PlanBaselineHistory::count();

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertSessionHasErrors('actual_completion_hours');

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Submitted, $workOrder->state);
        $this->assertSame('1200.00', (string) $workOrder->plan->refresh()->last_completion_hours);
        $this->assertSame($historyBefore, PlanBaselineHistory::count());
    }

    public function test_invalid_states_cannot_be_closed(): void
    {
        foreach (['WO-0001', 'WO-0002', 'WO-0005'] as $reference) {
            $workOrder = $this->workOrder($reference);
            $stateBefore = $workOrder->state;

            $this->actingAs($this->chief())
                ->post($this->closeUrl($workOrder))
                ->assertSessionHasErrors('work_order');

            $this->assertSame($stateBefore, $workOrder->refresh()->state);
        }
    }

    public function test_unauthorised_users_cannot_close_a_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->actingAs($this->tech1())
            ->post($this->closeUrl($workOrder))
            ->assertForbidden();

        $this->actingAs($this->viewer())
            ->post($this->closeUrl($workOrder))
            ->assertForbidden();

        $this->assertSame(WorkOrderState::Submitted, $workOrder->refresh()->state);
    }

    public function test_guest_is_redirected_from_close_endpoint(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->post($this->closeUrl($workOrder))->assertRedirect('/login');

        $this->assertSame(WorkOrderState::Submitted, $workOrder->refresh()->state);
    }

    public function test_repeated_closure_is_idempotent(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $historyAfterFirst = PlanBaselineHistory::where('work_order_id', $workOrder->id)->count();
        $closedEventsAfterFirst = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Closed)->count();
        $closedAuditsAfterFirst = AuditEvent::where('entity_id', $workOrder->id)
            ->where('action', 'work_order.closed')->count();

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertSessionHasErrors('work_order');

        $this->assertSame($historyAfterFirst, PlanBaselineHistory::where('work_order_id', $workOrder->id)->count());
        $this->assertSame($closedEventsAfterFirst, WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Closed)->count());
        $this->assertSame($closedAuditsAfterFirst, AuditEvent::where('entity_id', $workOrder->id)
            ->where('action', 'work_order.closed')->count());
        $this->assertSame(WorkOrderState::Closed, $workOrder->refresh()->state);
    }

    public function test_closing_a_work_order_releases_the_active_work_order_slot(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $plan = $workOrder->plan;
        $tech = $this->tech1();
        $chief = $this->chief();

        $this->actingAs($chief)
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertRedirect();

        $this->assertSame($before + 1, WorkOrder::count());
    }

    public function test_closure_does_not_change_unrelated_plans_or_work_orders(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $unrelatedPlan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $unrelatedWorkOrder = $this->workOrder('WO-0001');

        $planDateBefore = $unrelatedPlan->last_completion_date?->toDateString();
        $planHoursBefore = $unrelatedPlan->last_completion_hours !== null
            ? (string) $unrelatedPlan->last_completion_hours
            : null;
        $workOrderStateBefore = $unrelatedWorkOrder->state;
        $workOrderCountBefore = WorkOrder::count();

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $unrelatedPlan->refresh();
        $unrelatedWorkOrder->refresh();

        $this->assertSame($planDateBefore, $unrelatedPlan->last_completion_date?->toDateString());
        $this->assertSame($planHoursBefore, $unrelatedPlan->last_completion_hours !== null
            ? (string) $unrelatedPlan->last_completion_hours
            : null);
        $this->assertSame($workOrderStateBefore, $unrelatedWorkOrder->state);
        $this->assertSame($workOrderCountBefore + 0, WorkOrder::count());
    }

    public function test_closure_preserves_existing_meter_readings(): void
    {
        $workOrder = $this->workOrder('WO-0004');

        $meterCountBefore = MeterReading::count();
        $meterHashesBefore = MeterReading::orderBy('id')->pluck('reading_hours')->all();

        $this->actingAs($this->chief())
            ->post($this->closeUrl($workOrder))
            ->assertRedirect();

        $this->assertSame($meterCountBefore, MeterReading::count());
        $this->assertSame($meterHashesBefore, MeterReading::orderBy('id')->pluck('reading_hours')->all());
    }
}
