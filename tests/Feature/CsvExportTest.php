<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MaintenancePlan;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function chief(): User
    {
        return User::where('email', 'chief@cmms.test')->firstOrFail();
    }

    private function tech2(): User
    {
        return User::where('email', 'tech2@cmms.test')->firstOrFail();
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    private function parseCsv(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF");
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function test_maintenance_export_returns_csv_with_headers_and_records(): void
    {
        $response = $this->actingAs($this->chief())
            ->get(route('reports.maintenance.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(['Plan', 'Asset', 'Subsystem', 'Criticality', 'Trigger', 'Status', 'Trigger cause', 'Due date', 'Hours due at', 'Last completion date', 'Last completion hours', 'Interval days', 'Interval hours', 'Latest reading hours', 'Latest reading at'], $rows[0]);
        $this->assertSame(9, count($rows)); // 8 plans + header
    }

    public function test_work_order_export_includes_all_matching_records_across_pages(): void
    {
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $chief = $this->chief();
        $tech = $this->tech2();

        WorkOrder::factory()->closed()->count(25)->create([
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
            'created_by' => $chief->id,
        ]);

        $response = $this->actingAs($chief)->get(route('reports.work-orders.export'));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(32, count($rows)); // 31 records + header
        $this->assertSame('Reference', $rows[0][0]);
    }

    public function test_spare_export_preserves_exact_decimals_and_units(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.spares.export'));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertContains(['SP-001', 'Oil filter element', 'piece', '10.00', '6.00', 'OK', 'Yes'], $rows);
        $this->assertContains(['SP-005', 'Air compressor belt', 'piece', '3.00', '5.00', 'Below reorder', 'Yes'], $rows);
    }

    public function test_movement_export_includes_negative_quantities(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.movements.export'));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $issueRows = array_filter($rows, fn (array $row): bool => ($row[2] ?? '') === 'issue');
        $this->assertNotEmpty($issueRows);
        $this->assertSame('-2.00', array_values($issueRows)[0][3]);
    }

    public function test_export_filters_are_applied(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.spares.export', [
            'status' => 'low',
        ]));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $codes = array_column(array_slice($rows, 1), 0);

        $this->assertContains('SP-005', $codes);
        $this->assertContains('SP-006', $codes);
        $this->assertNotContains('SP-001', $codes);
    }

    public function test_empty_export_produces_header_only_csv(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.spares.export', [
            'search' => 'ZZZ-NO-MATCH',
        ]));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertSame(['Code', 'Description', 'Unit', 'Quantity on hand', 'Reorder level', 'Status', 'Active'], $rows[0]);
    }

    public function test_export_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->chief())
            ->get(route('reports.maintenance.export', ['status' => 'invalid']))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->chief())
            ->get(route('reports.work-orders.export', ['asset_id' => 'not-a-uuid']))
            ->assertSessionHasErrors('asset_id');

        $this->actingAs($this->chief())
            ->get(route('reports.movements.export', ['movement_type' => 'invalid']))
            ->assertSessionHasErrors('movement_type');
    }

    public function test_technician_export_visibility_is_restricted(): void
    {
        $response = $this->actingAs($this->tech2())->get(route('reports.work-orders.export'));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(3, count($rows) - 1);

        foreach (array_slice($rows, 1) as $row) {
            $workOrder = WorkOrder::where('reference', $row[0])->firstOrFail();
            $this->assertSame($this->tech2()->id, $workOrder->assigned_to);
        }
    }

    public function test_formula_injection_is_neutralised(): void
    {
        $chief = $this->chief();

        $item = SpareItem::factory()->create([
            'code' => '=BAD',
            'description' => '=SUM(1,2)',
            'unit' => 'piece',
            'quantity_on_hand' => 1,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        SpareMovement::factory()->create([
            'spare_item_id' => $item->id,
            'movement_type' => 'adjustment',
            'quantity_delta' => '1.00',
            'balance_after' => '2.00',
            'actor_id' => $chief->id,
            'reason' => "+injected\n\"quoted\"",
            'occurred_at' => now(),
        ]);

        $spareResponse = $this->actingAs($chief)->get(route('reports.spares.export'));
        $spareRows = $this->parseCsv($spareResponse->streamedContent());
        $maliciousRow = collect($spareRows)->firstWhere(0, "'=BAD");

        $this->assertNotNull($maliciousRow);
        $this->assertSame("'=BAD", $maliciousRow[0]);
        $this->assertSame("'=SUM(1,2)", $maliciousRow[1]);

        $movementResponse = $this->actingAs($chief)->get(route('reports.movements.export'));
        $movementRows = $this->parseCsv($movementResponse->streamedContent());
        $maliciousMovement = collect($movementRows)->first(fn (array $row): bool => str_starts_with((string) ($row[7] ?? ''), "'+injected"));

        $this->assertNotNull($maliciousMovement);
    }

    public function test_csv_handles_commas_quotes_and_unicode(): void
    {
        $chief = $this->chief();

        $item = SpareItem::factory()->create([
            'code' => 'TST-CSV',
            'description' => 'Line one, "quoted", café',
            'unit' => 'piece',
            'quantity_on_hand' => 1,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($chief)->get(route('reports.spares.export', [
            'search' => 'TST-CSV',
        ]));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame('Line one, "quoted", café', $rows[1][1]);
    }

    public function test_guests_and_inactive_users_cannot_export(): void
    {
        $this->get(route('reports.maintenance.export'))->assertRedirect('/login');

        $inactive = User::factory()->create([
            'role' => UserRole::Viewer,
            'is_active' => false,
        ]);

        $this->actingAs($inactive)->get(route('reports.maintenance.export'))->assertRedirect('/login');
    }

    public function test_unauthorized_technician_export_is_restricted(): void
    {
        $unassigned = WorkOrder::where('reference', 'WO-0001')->firstOrFail(); // assigned to tech1

        $response = $this->actingAs($this->tech2())->get(route('reports.work-orders.export', [
            'asset_id' => $unassigned->plan->asset_id,
        ]));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        foreach (array_slice($rows, 1) as $row) {
            $workOrder = WorkOrder::where('reference', $row[0])->firstOrFail();
            $this->assertSame($this->tech2()->id, $workOrder->assigned_to);
        }
    }

    public function test_exports_are_read_only(): void
    {
        $workOrderCount = WorkOrder::count();
        $movementCount = SpareMovement::count();

        $this->actingAs($this->chief())->get(route('reports.work-orders.export'))->assertOk();
        $this->actingAs($this->chief())->get(route('reports.movements.export'))->assertOk();

        $this->assertSame($workOrderCount, WorkOrder::count());
        $this->assertSame($movementCount, SpareMovement::count());
    }
}
