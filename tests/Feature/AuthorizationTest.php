<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_cannot_reach_role_protected_page(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_chief_engineer_can_view_users(): void
    {
        $chief = User::factory()->chiefEngineer()->create([
            'email' => 'chief@cmms.test',
        ]);

        $response = $this->actingAs($chief)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('Application users');
        $response->assertSee('chief@cmms.test');
    }

    public function test_technician_receives_forbidden_on_chief_engineer_page(): void
    {
        $technician = User::factory()->technician()->create();

        $response = $this->actingAs($technician)->get('/admin/users');

        $response->assertForbidden();
        $response->assertSee('Permission denied');
    }

    public function test_viewer_receives_forbidden_on_chief_engineer_page(): void
    {
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_inactive_user_session_is_terminated_on_next_request(): void
    {
        $inactive = User::factory()->inactive()->create();

        $response = $this->actingAs($inactive)->get('/dashboard');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_is_available_to_every_active_role(): void
    {
        $users = [
            User::factory()->chiefEngineer()->create(),
            User::factory()->technician()->create(),
            User::factory()->viewer()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }
}
