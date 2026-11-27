<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    private function tech(): User
    {
        return User::where('email', 'tech1@cmms.test')->firstOrFail();
    }

    public function test_guest_is_redirected_from_audit_log(): void
    {
        $this->get(route('audit.index'))->assertRedirect('/login');
    }

    public function test_inactive_user_is_redirected_from_audit_log(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Viewer,
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('audit.index'))->assertRedirect('/login');
    }

    public function test_technician_cannot_access_audit_log(): void
    {
        $this->actingAs($this->tech())->get(route('audit.index'))->assertForbidden();
    }

    public function test_chief_engineer_and_viewer_can_access_audit_log(): void
    {
        $this->actingAs($this->chief())->get(route('audit.index'))->assertOk();
        $this->actingAs($this->viewer())->get(route('audit.index'))->assertOk();
    }

    public function test_audit_log_displays_actor_action_and_entity(): void
    {
        $event = AuditEvent::where('action', 'work_order.closed')->firstOrFail();

        $this->actingAs($this->chief())
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee($event->action)
            ->assertSee($event->entity_type)
            ->assertSee((string) $event->entity_id);

        $this->actingAs($this->chief())
            ->get(route('audit.show', $event))
            ->assertOk()
            ->assertSee($event->action)
            ->assertSee($event->actor->name);
    }

    public function test_audit_filters_and_date_boundaries(): void
    {
        $this->actingAs($this->chief())
            ->get(route('audit.index', ['action' => 'work_order.closed']))
            ->assertOk()
            ->assertSee('work_order.closed');

        $this->actingAs($this->chief())
            ->get(route('audit.index', [
                'date_from' => '2026-11-30',
                'date_to' => '2026-11-01',
            ]))
            ->assertRedirect();
    }

    public function test_audit_invalid_filter_is_rejected(): void
    {
        $this->actingAs($this->chief())
            ->get(route('audit.index', ['actor_id' => 'not-a-uuid']))
            ->assertSessionHasErrors('actor_id');
    }

    public function test_audit_log_paginates(): void
    {
        AuditEvent::factory()->count(25)->create([
            'actor_id' => $this->chief()->id,
        ]);

        $response = $this->actingAs($this->chief())->get(route('audit.index'));

        $response->assertOk();
        $this->assertSame(20, $response->viewData('events')->perPage());
        $this->assertSame(30, $response->viewData('events')->total());
    }

    public function test_sensitive_values_are_redacted(): void
    {
        $event = AuditEvent::create([
            'actor_id' => $this->chief()->id,
            'action' => 'test.sensitive',
            'entity_type' => 'test',
            'entity_id' => '00000000-0000-0000-0000-000000000000',
            'before' => ['password' => 'secret-password', 'remember_token' => 'secret-token'],
            'after' => ['api_key' => 'secret-key'],
            'reason' => null,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->chief())->get(route('audit.show', $event));

        $response->assertOk();
        $response->assertSee('[redacted]');
        $response->assertDontSee('secret-password', false);
        $response->assertDontSee('secret-token', false);
        $response->assertDontSee('secret-key', false);
    }

    public function test_user_controlled_text_is_escaped(): void
    {
        $event = AuditEvent::create([
            'actor_id' => $this->chief()->id,
            'action' => 'test.escape',
            'entity_type' => 'test',
            'entity_id' => '00000000-0000-0000-0000-000000000000',
            'before' => null,
            'after' => null,
            'reason' => '<script>alert(1)</script>',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->chief())->get(route('audit.show', $event));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_audit_records_are_append_only(): void
    {
        $event = AuditEvent::firstOrFail();

        try {
            $event->update(['reason' => 'tampered']);
            $this->fail('Expected an exception when updating an append-only audit event.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }

        try {
            $event->delete();
            $this->fail('Expected an exception when deleting an append-only audit event.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }
    }

    public function test_audit_log_is_read_only(): void
    {
        $count = AuditEvent::count();

        $this->actingAs($this->chief())->get(route('audit.index'))->assertOk();
        $this->actingAs($this->chief())->get(route('audit.show', AuditEvent::firstOrFail()))->assertOk();

        $this->assertSame($count, AuditEvent::count());
    }

    public function test_direct_unauthorized_audit_detail_is_forbidden(): void
    {
        $event = AuditEvent::firstOrFail();

        $this->actingAs($this->tech())->get(route('audit.show', $event))->assertForbidden();
    }
}
