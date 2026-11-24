<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_asset_code_is_rejected(): void
    {
        DB::table('vessels')->insert([
            'id' => '01900000-0000-4000-8000-000000000001',
            'code' => 'V1', 'name' => 'V', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assets')->insert([
            'id' => '01900000-0000-4000-8000-000000000a01',
            'vessel_id' => '01900000-0000-4000-8000-000000000001',
            'code' => 'A01', 'name' => 'a', 'subsystem' => 's', 'criticality' => 'low',
            'service_status' => 'in_service', 'has_hour_meter' => false, 'meter_unit' => 'hours',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('assets')->insert([
            'id' => '01900000-0000-4000-8000-000000000a02',
            'vessel_id' => '01900000-0000-4000-8000-000000000001',
            'code' => 'A01', 'name' => 'b', 'subsystem' => 's', 'criticality' => 'low',
            'service_status' => 'in_service', 'has_hour_meter' => false, 'meter_unit' => 'hours',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_interval_days_must_be_positive(): void
    {
        $this->seedBaseVesselAndAsset();
        $this->expectException(QueryException::class);
        DB::table('maintenance_plans')->insert([
            'id' => '01900000-0000-4000-8000-000000000c01',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'code' => 'PX', 'task_description' => 't', 'trigger_type' => 'calendar',
            'interval_days' => 0, 'source_label' => 'x', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_active_plan_requires_complete_baseline(): void
    {
        $this->seedBaseVesselAndAsset();
        $this->expectException(QueryException::class);
        DB::table('maintenance_plans')->insert([
            'id' => '01900000-0000-4000-8000-000000000c02',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'code' => 'PY', 'task_description' => 't', 'trigger_type' => 'calendar',
            'interval_days' => 30, 'last_completion_date' => null,
            'source_label' => 'x', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_inactive_plan_may_be_incomplete(): void
    {
        $this->seedBaseVesselAndAsset();
        DB::table('maintenance_plans')->insert([
            'id' => '01900000-0000-4000-8000-000000000c03',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'code' => 'PZ', 'task_description' => 't', 'trigger_type' => 'calendar',
            'interval_days' => 30, 'last_completion_date' => null,
            'source_label' => 'x', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('maintenance_plans', ['code' => 'PZ', 'is_active' => false]);
    }

    public function test_quantity_on_hand_cannot_be_negative(): void
    {
        $this->expectException(QueryException::class);
        DB::table('spare_items')->insert([
            'id' => '01900000-0000-4000-8000-000000000d01',
            'code' => 'SX', 'description' => 'd', 'unit' => 'piece',
            'quantity_on_hand' => -1, 'reorder_level' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_issue_movement_must_be_negative(): void
    {
        $this->seedSpare();
        $this->expectException(QueryException::class);
        DB::table('spare_movements')->insert([
            'id' => '01900000-0000-4000-8000-000000000f01',
            'spare_item_id' => '01900000-0000-4000-8000-000000000d01',
            'movement_type' => 'issue', 'quantity_delta' => 2, 'balance_after' => 5,
            'actor_id' => User::factory()->create()->id,
            'work_order_id' => null, 'occurred_at' => now(),
        ]);
    }

    public function test_returned_event_requires_reason(): void
    {
        $this->seedWorkOrder();
        $woId = DB::table('work_orders')->value('id');
        $userId = User::factory()->create()->id;
        $this->expectException(QueryException::class);
        DB::table('work_order_events')->insert([
            'id' => '01900000-0000-4000-8000-000000000e01',
            'work_order_id' => $woId, 'event_type' => 'returned',
            'actor_id' => $userId, 'occurred_at' => now(),
        ]);
    }

    public function test_partial_unique_index_blocks_second_active_work_order(): void
    {
        $this->seedPlanWithActiveWorkOrder();
        $planId = DB::table('maintenance_plans')->value('id');
        $userId = User::factory()->create()->id;

        $this->expectException(QueryException::class);
        DB::table('work_orders')->insert([
            'id' => '01900000-0000-4000-8000-000000000e02',
            'reference' => 'WO-DUP', 'plan_id' => $planId, 'state' => 'open',
            'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    public function test_closed_state_requires_closed_at(): void
    {
        $this->seedPlanWithActiveWorkOrder();
        $planId = DB::table('maintenance_plans')->value('id');
        $userId = User::factory()->create()->id;
        $this->expectException(QueryException::class);
        DB::table('work_orders')->insert([
            'id' => '01900000-0000-4000-8000-000000000e03',
            'reference' => 'WO-NOCLOSE', 'plan_id' => $planId, 'state' => 'closed',
            'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    public function test_audit_event_is_append_only(): void
    {
        $id = DB::table('audit_events')->insertGetId([
            'action' => 'x', 'entity_type' => 'y', 'entity_id' => '01900000-0000-4000-8000-000000000001',
            'occurred_at' => now(),
        ], 'id');

        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $id)->update(['action' => 'z']);
    }

    public function test_meter_reading_cannot_decrease(): void
    {
        $this->seedBaseVesselAndAsset();
        DB::table('assets')->where('id', '01900000-0000-4000-8000-000000000a01')->update(['has_hour_meter' => true]);
        $userId = User::factory()->create()->id;
        DB::table('meter_readings')->insert([
            'id' => '01900000-0000-4000-8000-000000000901',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'reading_hours' => 100.00, 'reading_at' => now()->subDay(),
            'recorded_by' => $userId, 'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('meter_readings')->insert([
            'id' => '01900000-0000-4000-8000-000000000902',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'reading_hours' => 50.00, 'reading_at' => now(),
            'recorded_by' => $userId, 'created_at' => now(),
        ]);
    }

    public function test_foreign_key_restricts_vessel_deletion(): void
    {
        $this->seedBaseVesselAndAsset();
        $this->expectException(QueryException::class);
        DB::table('vessels')->where('id', '01900000-0000-4000-8000-000000000001')->delete();
    }

    private function seedBaseVesselAndAsset(): void
    {
        DB::table('vessels')->insert([
            'id' => '01900000-0000-4000-8000-000000000001',
            'code' => 'V1', 'name' => 'V', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assets')->insert([
            'id' => '01900000-0000-4000-8000-000000000a01',
            'vessel_id' => '01900000-0000-4000-8000-000000000001',
            'code' => 'A01', 'name' => 'a', 'subsystem' => 's', 'criticality' => 'low',
            'service_status' => 'in_service', 'has_hour_meter' => false, 'meter_unit' => 'hours',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedSpare(): void
    {
        DB::table('spare_items')->insert([
            'id' => '01900000-0000-4000-8000-000000000d01',
            'code' => 'SX', 'description' => 'd', 'unit' => 'piece',
            'quantity_on_hand' => 5, 'reorder_level' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedWorkOrder(): void
    {
        $this->seedBaseVesselAndAsset();
        $userId = User::factory()->create()->id;
        DB::table('maintenance_plans')->insert([
            'id' => '01900000-0000-4000-8000-000000000c99',
            'asset_id' => '01900000-0000-4000-8000-000000000a01',
            'code' => 'PZ', 'task_description' => 't', 'trigger_type' => 'calendar',
            'interval_days' => 30, 'last_completion_date' => '2026-11-01',
            'source_label' => 'x', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('work_orders')->insert([
            'id' => '01900000-0000-4000-8000-000000000e01',
            'reference' => 'WO-T', 'plan_id' => '01900000-0000-4000-8000-000000000c99',
            'state' => 'open', 'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    private function seedPlanWithActiveWorkOrder(): void
    {
        $this->seedWorkOrder();
    }
}
