<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\Vessel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_roles_can_view_vessel_assets_and_plans(): void
    {
        foreach (['chief@cmms.test', 'tech1@cmms.test', 'viewer@cmms.test'] as $email) {
            $user = User::where('email', $email)->first();
            $this->actingAs($user)->get('/vessel')->assertOk();
            $this->actingAs($user)->get('/assets')->assertOk();
            $this->actingAs($user)->get('/plans')->assertOk();
        }
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/assets')->assertRedirect('/login');
        $this->get('/plans')->assertRedirect('/login');
        $this->get('/vessel')->assertRedirect('/login');
    }

    public function test_technician_cannot_create_asset(): void
    {
        $user = User::where('email', 'tech1@cmms.test')->first();
        $this->actingAs($user)->get('/assets/create')->assertForbidden();
        $this->actingAs($user)->post('/assets', [])->assertForbidden();
    }

    public function test_viewer_cannot_create_plan(): void
    {
        $user = User::where('email', 'viewer@cmms.test')->first();
        $this->actingAs($user)->get('/plans/create')->assertForbidden();
    }

    public function test_chief_can_create_asset(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->first();
        $vessel = Vessel::first();
        $response = $this->actingAs($chief)->post('/assets', [
            'vessel_id' => $vessel->id,
            'code' => 'Z99',
            'name' => 'Test asset',
            'subsystem' => 'Propulsion',
            'criticality' => 'low',
            'service_status' => 'in_service',
            'has_hour_meter' => '1',
        ]);
        $response->assertRedirect(route('assets.index'));
        $this->assertDatabaseHas('assets', ['code' => 'Z99']);
    }

    public function test_running_hour_plan_requires_metered_asset(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->first();
        $asset = Asset::where('has_hour_meter', false)->first();
        $response = $this->actingAs($chief)->post('/plans', [
            'asset_id' => $asset->id,
            'code' => 'ZP2',
            'task_description' => 't',
            'trigger_type' => 'hours',
            'interval_hours' => 100,
            'last_completion_hours' => 500,
            'source_label' => 'Synthetic teaching scenario',
        ]);
        $response->assertSessionHasErrors('asset_id');
        $this->assertDatabaseMissing('maintenance_plans', ['code' => 'ZP2']);
    }

    public function test_asset_search_filters(): void
    {
        $user = User::where('email', 'viewer@cmms.test')->first();
        $response = $this->actingAs($user)->get('/assets?search=Port');
        $response->assertOk();
        $response->assertSee('Port main engine');
    }

    public function test_non_chief_cannot_mutate_assets_or_plans(): void
    {
        foreach (['tech1@cmms.test', 'viewer@cmms.test'] as $email) {
            $user = User::where('email', $email)->first();
            $plan = MaintenancePlan::first();
            $asset = Asset::first();
            $vessel = Vessel::first();

            $this->actingAs($user)->withoutMiddleware(ValidateCsrfToken::class)
                ->post('/assets', ['vessel_id' => $vessel->id, 'code' => 'X', 'name' => 'x', 'subsystem' => 's', 'criticality' => 'low', 'service_status' => 'in_service'])
                ->assertForbidden();

            $this->actingAs($user)->withoutMiddleware(ValidateCsrfToken::class)
                ->put("/assets/{$asset->id}", ['vessel_id' => $vessel->id, 'code' => $asset->code, 'name' => $asset->name, 'subsystem' => $asset->subsystem, 'criticality' => 'low', 'service_status' => 'in_service'])
                ->assertForbidden();

            $this->actingAs($user)->withoutMiddleware(ValidateCsrfToken::class)
                ->post("/plans/{$plan->id}/activate")
                ->assertForbidden();

            $this->actingAs($user)->withoutMiddleware(ValidateCsrfToken::class)
                ->delete("/plans/{$plan->id}/activate")
                ->assertForbidden();
        }
    }

    public function test_invalid_plan_trigger_combination_rejected(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->first();
        $asset = Asset::where('has_hour_meter', true)->first();
        $response = $this->actingAs($chief)->post('/plans', [
            'asset_id' => $asset->id,
            'code' => 'ZP3',
            'task_description' => 't',
            'trigger_type' => 'calendar',
            'interval_hours' => 100,
            'source_label' => 'Synthetic teaching scenario',
        ]);
        $response->assertSessionHasErrors('interval_days');
        $this->assertDatabaseMissing('maintenance_plans', ['code' => 'ZP3']);
    }

    public function test_plan_edit_preserves_baseline_when_not_supplied(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->first();
        $plan = MaintenancePlan::where('code', 'P03')->first();
        $before = $plan->last_completion_date?->toDateString();
        $this->actingAs($chief)->put("/plans/{$plan->id}", [
            'asset_id' => $plan->asset_id,
            'code' => $plan->code,
            'task_description' => 'updated description',
            'trigger_type' => $plan->trigger_type->value,
            'interval_days' => $plan->interval_days,
            'source_label' => $plan->source_label,
        ]);
        $plan->refresh();
        $this->assertSame($before, $plan->last_completion_date?->toDateString());
        $this->assertSame('updated description', $plan->task_description);
        // restore
        $plan->update(['task_description' => 'Synthetic teaching scenario maintenance task for A08.']);
    }
}
