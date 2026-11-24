<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_accounts_cover_every_role_and_an_inactive_user(): void
    {
        config(['cmms.seed_password' => 'testing-seed-password']);

        $this->seed(UserSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertSame(1, User::where('role', UserRole::ChiefEngineer)->count());
        $this->assertSame(2, User::where('role', UserRole::Technician)->count());
        $this->assertSame(2, User::where('role', UserRole::Viewer)->count());
        $this->assertSame(1, User::where('is_active', false)->count());

        $this->assertDatabaseHas('users', [
            'email' => 'chief@cmms.test',
            'role' => UserRole::ChiefEngineer->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'inactive@cmms.test',
            'is_active' => false,
        ]);
    }

    public function test_seeder_requires_a_non_placeholder_password(): void
    {
        config(['cmms.seed_password' => 'change-me']);

        $this->expectException(\RuntimeException::class);

        $this->seed(UserSeeder::class);
    }
}
