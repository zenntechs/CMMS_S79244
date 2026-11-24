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
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'role' => $account['role'],
                    'is_active' => $account['is_active'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
