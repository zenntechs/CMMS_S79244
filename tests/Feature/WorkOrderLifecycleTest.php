<?php

namespace Tests\Feature;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderLifecycleTest extends TestCase
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

    private function completionPayload(array $overrides = []): array
    {
        return $overrides + [
            'actual_completion_date' => '2026-11-25',
            'completion_notes' => 'Completed inspection and replaced the worn filter element.',
            'evidence_ref' => 'EVD-TEST-001',
        ];
    }

    public function test_assigned_technician_can_start_an_open_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0001');
        $tech = $this->tech1();

        $this->actingAs($tech)
            ->post(route('work-orders.start', $workOrder))
            ->assertRedirect(route('work-orders.show', $workOrder));

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::InProgress, $workOrder->state);
        $this->assertNotNull($workOrder->started_at);

        $event = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Started)->sole();
        $this->assertSame($tech->id, $event->actor_id);
        $this->assertSame(WorkOrderState::Open, $event->from_state);
        $this->assertSame(WorkOrderState::InProgress, $event->to_state);

        $audit = AuditEvent::where('action', 'work_order.started')
            ->where('entity_id', $workOrder->id)->sole();
        $this->assertSame($tech->id, $audit->actor_id);
        $this->assertSame('open', $audit->before['state']);
        $this->assertSame('in_progress', $audit->after['state']);
    }

    public function test_chief_engineer_can_start_an_open_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0001');
        $chief = $this->chief();

        $this->actingAs($chief)
            ->post(route('work-orders.start', $workOrder))
            ->assertRedirect();

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
        $this->assertSame(
            $chief->id,
            WorkOrderEvent::where('work_order_id', $workOrder->id)
                ->where('event_type', WorkOrderEventType::Started)->sole()->actor_id,
        );
    }

    public function test_unassigned_technician_cannot_start_a_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0001');

        $this->actingAs($this->tech2())
            ->post(route('work-orders.start', $workOrder))
            ->assertForbidden();

        $this->assertSame(WorkOrderState::Open, $workOrder->refresh()->state);
    }

    public function test_viewer_and_guest_cannot_start_a_work_order(): void
    {
        $workOrder = $this->workOrder('WO-0001');

        $this->actingAs($this->viewer())
            ->post(route('work-orders.start', $workOrder))
            ->assertForbidden();

        auth()->logout();

        $this->post(route('work-orders.start', $workOrder))->assertRedirect('/login');

        $this->assertSame(WorkOrderState::Open, $workOrder->refresh()->state);
    }

    public function test_an_already_started_work_order_cannot_be_started_again(): void
    {
        $workOrder = $this->workOrder('WO-0001');

        $this->actingAs($this->tech1())
            ->post(route('work-orders.start', $workOrder))
            ->assertRedirect();

        $eventsBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)->count();

        $this->actingAs($this->tech1())
            ->post(route('work-orders.start', $workOrder))
            ->assertSessionHasErrors('work_order');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
        $this->assertSame($eventsBefore, WorkOrderEvent::where('work_order_id', $workOrder->id)->count());
    }

    public function test_assigned_technician_can_submit_completion_for_a_calendar_plan(): void
    {
        $workOrder = $this->workOrder('WO-0002'); // calendar plan P02, assigned to tech2
        $tech = $this->tech2();

        $this->actingAs($tech)
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_hours' => '9999.00',
            ]))
            ->assertRedirect(route('work-orders.show', $workOrder));

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Submitted, $workOrder->state);
        $this->assertNotNull($workOrder->submitted_at);
        $this->assertSame('2026-11-25', $workOrder->actual_completion_date->format('Y-m-d'));
        $this->assertNull($workOrder->actual_completion_hours);
        $this->assertSame('Completed inspection and replaced the worn filter element.', $workOrder->completion_notes);

        $event = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Submitted)->sole();
        $this->assertSame($tech->id, $event->actor_id);
        $this->assertSame(WorkOrderState::InProgress, $event->from_state);
        $this->assertSame(WorkOrderState::Submitted, $event->to_state);
        $this->assertSame('2026-11-25', $event->snapshot['actual_completion_date']);

        $audit = AuditEvent::where('action', 'work_order.submitted')
            ->where('entity_id', $workOrder->id)->sole();
        $this->assertSame($tech->id, $audit->actor_id);
        $this->assertSame('submitted', $audit->after['state']);
    }

    public function test_chief_engineer_can_submit_completion(): void
    {
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->chief())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertRedirect();

        $this->assertSame(WorkOrderState::Submitted, $workOrder->refresh()->state);
    }

    public function test_hours_plan_requires_completion_hours(): void
    {
        $workOrder = $this->inProgressHoursWorkOrder();

        $this->actingAs($this->tech1())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertSessionHasErrors('actual_completion_hours');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
        $this->assertNull($workOrder->submitted_at);
    }

    public function test_completion_hours_cannot_be_below_the_plan_baseline(): void
    {
        $workOrder = $this->inProgressHoursWorkOrder();

        $this->actingAs($this->tech1())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_hours' => '799.99',
            ]))
            ->assertSessionHasErrors('actual_completion_hours');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_dual_plan_accepts_date_and_hours_when_both_are_supplied(): void
    {
        $workOrder = $this->workOrder('WO-0001'); // dual plan P07, baseline date 2026-11-01, hours 1200
        $tech = $this->tech1();

        $this->actingAs($tech)->post(route('work-orders.start', $workOrder))->assertRedirect();

        $this->actingAs($tech)
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_hours' => '1270.50',
            ]))
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Submitted, $workOrder->state);
        $this->assertSame('1270.50', $workOrder->actual_completion_hours);
    }

    public function test_dual_plan_requires_completion_hours(): void
    {
        $workOrder = $this->workOrder('WO-0001'); // dual plan

        $this->actingAs($this->tech1())->post(route('work-orders.start', $workOrder));

        $this->actingAs($this->tech1())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertSessionHasErrors('actual_completion_hours');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_completion_date_cannot_precede_the_plan_baseline(): void
    {
        $workOrder = $this->workOrder('WO-0001'); // baseline date 2026-11-01

        $this->actingAs($this->tech1())->post(route('work-orders.start', $workOrder));

        $this->actingAs($this->tech1())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_date' => '2026-10-31',
                'actual_completion_hours' => '1270.00',
            ]))
            ->assertSessionHasErrors('actual_completion_date');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_completion_date_cannot_be_in_the_future(): void
    {
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->tech2())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_date' => '2026-11-26',
            ]))
            ->assertSessionHasErrors('actual_completion_date');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_completion_notes_are_required(): void
    {
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->tech2())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'completion_notes' => '',
            ]))
            ->assertSessionHasErrors('completion_notes');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_unassigned_technician_cannot_submit_completion(): void
    {
        $workOrder = $this->workOrder('WO-0002'); // assigned to tech2

        $this->actingAs($this->tech1())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertForbidden();

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_viewer_cannot_submit_completion(): void
    {
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->viewer())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertForbidden();

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_failed_submission_leaves_no_partial_state_event_or_audit(): void
    {
        $workOrder = $this->workOrder('WO-0002');
        $eventsBefore = WorkOrderEvent::count();
        $auditsBefore = AuditEvent::count();

        $this->actingAs($this->tech2())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'completion_notes' => '',
            ]))
            ->assertSessionHasErrors('completion_notes');

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::InProgress, $workOrder->state);
        $this->assertNull($workOrder->submitted_at);
        $this->assertNull($workOrder->actual_completion_date);
        $this->assertSame($eventsBefore, WorkOrderEvent::count());
        $this->assertSame($auditsBefore, AuditEvent::count());
    }

    public function test_chief_engineer_can_return_submitted_work_with_a_reason(): void
    {
        $workOrder = $this->workOrder('WO-0003'); // submitted, assigned to tech1
        $chief = $this->chief();

        $this->actingAs($chief)
            ->post(route('work-orders.return', $workOrder), [
                'reason' => 'Please re-check the filter torque values and attach the checklist.',
            ])
            ->assertRedirect(route('work-orders.show', $workOrder));

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::InProgress, $workOrder->state);
        $this->assertSame('Completed inspection and filter change.', $workOrder->completion_notes);

        $returned = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Returned)
            ->orderByDesc('occurred_at')->firstOrFail();
        $this->assertSame($chief->id, $returned->actor_id);
        $this->assertSame('Please re-check the filter torque values and attach the checklist.', $returned->reason);
        $this->assertSame('1420.00', $returned->snapshot['actual_completion_hours']);
        $this->assertNotNull($returned->snapshot['submitted_at']);

        $audit = AuditEvent::where('action', 'work_order.returned')
            ->where('entity_id', $workOrder->id)->sole();
        $this->assertSame($chief->id, $audit->actor_id);
        $this->assertSame('submitted', $audit->before['state']);
        $this->assertSame('in_progress', $audit->after['state']);
    }

    public function test_return_reason_is_mandatory(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $returnedBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Returned)->count();

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(WorkOrderState::Submitted, $workOrder->refresh()->state);
        $this->assertSame(
            $returnedBefore,
            WorkOrderEvent::where('work_order_id', $workOrder->id)
                ->where('event_type', WorkOrderEventType::Returned)->count(),
        );
    }

    public function test_technician_and_viewer_cannot_return_work(): void
    {
        $workOrder = $this->workOrder('WO-0003');

        $this->actingAs($this->tech1())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'Attempted return.'])
            ->assertForbidden();

        $this->actingAs($this->viewer())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'Attempted return.'])
            ->assertForbidden();

        $this->assertSame(WorkOrderState::Submitted, $workOrder->refresh()->state);
    }

    public function test_non_submitted_work_cannot_be_returned(): void
    {
        $workOrder = $this->workOrder('WO-0002'); // in progress

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'Not submitted yet.'])
            ->assertSessionHasErrors('work_order');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
    }

    public function test_returned_work_can_be_resubmitted_and_keeps_submission_history(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $tech = $this->tech1();
        $chief = $this->chief();
        $returnedBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Returned)->count();

        $this->actingAs($chief)->post(route('work-orders.return', $workOrder), [
            'reason' => 'Recalculate the completion hours from the meter log.',
        ])->assertRedirect();

        $this->actingAs($tech)
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload([
                'actual_completion_hours' => '1440.00',
                'completion_notes' => 'Rechecked the filter torque and recalculated the running hours.',
            ]))
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Submitted, $workOrder->state);
        $this->assertSame('1440.00', $workOrder->actual_completion_hours);

        $this->assertSame(
            3,
            WorkOrderEvent::where('work_order_id', $workOrder->id)
                ->where('event_type', WorkOrderEventType::Submitted)->count(),
        );

        // The earlier submission remains preserved in the returned event snapshot.
        $returned = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Returned)
            ->orderByDesc('occurred_at')->firstOrFail();
        $this->assertSame('1420.00', $returned->snapshot['actual_completion_hours']);
        $this->assertSame(
            $returnedBefore + 1,
            WorkOrderEvent::where('work_order_id', $workOrder->id)
                ->where('event_type', WorkOrderEventType::Returned)->count(),
        );
    }

    public function test_work_can_only_be_returned_once_per_submission(): void
    {
        $workOrder = $this->workOrder('WO-0003');
        $returnedBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)
            ->where('event_type', WorkOrderEventType::Returned)->count();

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'First return reason.'])
            ->assertRedirect();

        $this->actingAs($this->chief())
            ->post(route('work-orders.return', $workOrder), ['reason' => 'Second return attempt.'])
            ->assertSessionHasErrors('work_order');

        $this->assertSame(WorkOrderState::InProgress, $workOrder->refresh()->state);
        $this->assertSame(
            $returnedBefore + 1,
            WorkOrderEvent::where('work_order_id', $workOrder->id)
                ->where('event_type', WorkOrderEventType::Returned)->count(),
        );
    }

    public function test_closed_work_orders_cannot_be_started_or_submitted(): void
    {
        $workOrder = $this->workOrder('WO-0005');
        $closedAt = $workOrder->closed_at;
        $eventsBefore = WorkOrderEvent::where('work_order_id', $workOrder->id)->count();

        $this->actingAs($this->chief())
            ->post(route('work-orders.start', $workOrder))
            ->assertSessionHasErrors('work_order');

        $this->actingAs($this->chief())
            ->post(route('work-orders.submit', $workOrder), $this->completionPayload())
            ->assertSessionHasErrors('work_order');

        $workOrder->refresh();
        $this->assertSame(WorkOrderState::Closed, $workOrder->state);
        $this->assertEquals($closedAt, $workOrder->closed_at);
        $this->assertSame($eventsBefore, WorkOrderEvent::where('work_order_id', $workOrder->id)->count());
    }

    public function test_transitions_do_not_advance_baselines_or_create_meter_readings(): void
    {
        $plan = MaintenancePlan::where('code', 'P07')->firstOrFail();
        $baselineDate = $plan->last_completion_date->toDateString();
        $baselineHours = (string) $plan->last_completion_hours;
        $meterCount = MeterReading::count();
        $meterHashes = MeterReading::orderBy('id')->pluck('reading_hours')->all();

        $workOrder = $this->workOrder('WO-0001');

        $this->actingAs($this->tech1())->post(route('work-orders.start', $workOrder));
        $this->actingAs($this->tech1())->post(route('work-orders.submit', $workOrder), $this->completionPayload([
            'actual_completion_hours' => '1270.00',
        ]));

        $this->actingAs($this->chief())->post(route('work-orders.return', $workOrder), [
            'reason' => 'Return for a final check of the recorded hours.',
        ]);

        $plan->refresh();
        $this->assertSame($baselineDate, $plan->last_completion_date->toDateString());
        $this->assertSame($baselineHours, (string) $plan->last_completion_hours);
        $this->assertNull($plan->last_completion_work_order_id);
        $this->assertSame($meterCount, MeterReading::count());
        $this->assertSame($meterHashes, MeterReading::orderBy('id')->pluck('reading_hours')->all());
        $this->assertSame(0, $plan->baselineHistory()->count());
    }

    public function test_work_order_detail_shows_permitted_actions_only(): void
    {
        $open = $this->workOrder('WO-0001');
        $submitted = $this->workOrder('WO-0003');

        $this->actingAs($this->tech1())
            ->get(route('work-orders.show', $open))
            ->assertOk()
            ->assertSee('Start work')
            ->assertDontSee('Submit for approval');

        $this->actingAs($this->tech1())
            ->get(route('work-orders.show', $submitted))
            ->assertOk()
            ->assertSee('awaiting Chief Engineer review')
            ->assertDontSee('Return to technician');

        $this->actingAs($this->chief())
            ->get(route('work-orders.show', $submitted))
            ->assertOk()
            ->assertSee('Return to technician')
            ->assertSee('Return reason');
    }

    private function inProgressHoursWorkOrder(): WorkOrder
    {
        $plan = MaintenancePlan::where('code', 'P06')->firstOrFail(); // hours plan, baseline 800.00
        $tech = $this->tech1();

        return WorkOrder::factory()->inProgress()->create([
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
            'created_by' => $this->chief()->id,
            'started_at' => now()->subHour(),
        ]);
    }
}
