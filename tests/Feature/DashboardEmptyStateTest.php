<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_handles_empty_datasets(): void
    {
        $user = User::factory()->create([
            'name' => 'Chief Engineer',
            'email' => 'chief-empty@cmms.test',
            'password' => bcrypt('password'),
            'role' => UserRole::ChiefEngineer,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No maintenance plans require attention.');
        $response->assertSee('No active spare items are below reorder level.');
    }
}
