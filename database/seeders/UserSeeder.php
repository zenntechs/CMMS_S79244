<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed the synthetic accounts used by the assignment.
     */
    public function run(): void
    {
        $password = config('cmms.seed_password');

        if (! is_string($password) || $password === '' || $password === 'change-me') {
            throw new RuntimeException(
                'Set a non-placeholder CMMS_SEED_PASSWORD in .env before seeding users.'
            );
        }

        $accounts = [
            [
                'name' => 'Chief Engineer',
                'email' => 'chief@cmms.test',
                'role' => UserRole::ChiefEngineer,
                'is_active' => true,
            ],
            [
                'name' => 'Technician One',
                'email' => 'tech1@cmms.test',
                'role' => UserRole::Technician,
                'is_active' => true,
            ],
            [
                'name' => 'Technician Two',
                'email' => 'tech2@cmms.test',
                'role' => UserRole::Technician,
                'is_active' => true,
            ],
            [
                'name' => 'Viewer',
                'email' => 'viewer@cmms.test',
                'role' => UserRole::Viewer,
                'is_active' => true,
            ],
            [
                'name' => 'Inactive Account',
                'email' => 'inactive@cmms.test',
                'role' => UserRole::Viewer,
                'is_active' => false,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);

            $user->name = $account['name'];
            $user->role = $account['role'];
            $user->is_active = $account['is_active'];
            $user->email_verified_at = $user->email_verified_at ?? now();

            // Credentials are only written when the account is first created so
            // that re-seeding never overwrites an existing password hash.
            if (! $user->exists) {
                $user->password = Hash::make($password);
            }

            $user->save();
        }
    }
}
