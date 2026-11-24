<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_tables_exist(): void
    {
        $expected = [
            'vessels', 'assets', 'meter_readings', 'maintenance_plans', 'work_orders',
            'work_order_events', 'spare_items', 'spare_movements',
            'plan_baseline_history', 'audit_events',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Missing table {$table}"
            );
        }
    }

    public function test_required_enum_types_exist(): void
    {
        $types = DB::select("select typname from pg_type where typtype = 'e' order by typname");
        $names = array_column($types, 'typname');
        foreach (['criticality', 'plan_trigger', 'service_status', 'user_role', 'work_order_state'] as $type) {
            $this->assertContains($type, $names);
        }
    }

    public function test_partial_unique_index_exists(): void
    {
        $indexes = DB::select("select indexname from pg_indexes where schemaname = 'public'");
        $names = array_column($indexes, 'indexname');
        $this->assertContains('one_active_work_order_per_plan', $names);
    }

    public function test_uuid_primary_keys(): void
    {
        foreach (['vessels', 'assets', 'work_orders'] as $table) {
            $columns = DB::select("select data_type from information_schema.columns where table_name = ? and column_name = 'id'", [$table]);
            $this->assertSame('uuid', $columns[0]->data_type, "{$table}.id must be uuid");
        }
    }

    public function test_history_tables_use_bigint_primary_keys(): void
    {
        foreach (['audit_events', 'plan_baseline_history'] as $table) {
            $columns = DB::select("select data_type from information_schema.columns where table_name = ? and column_name = 'id'", [$table]);
            $this->assertSame('bigint', $columns[0]->data_type, "{$table}.id must be bigint");
        }
    }
}
