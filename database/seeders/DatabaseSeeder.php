<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
{
    User::query()->updateOrCreate(
        ['email' => 'admin@sig.com'],
        [
            'name' => 'Admin',
            'role' => 'Manager',
            'password' => Hash::make('password123'),
        ],
    );

    User::query()->updateOrCreate(
        ['email' => 'manager1@hcm.com'],
        [
            'name' => 'manager1',
            'role' => 'Manager',
            'password' => Hash::make('password123'),
        ],
    );

    User::query()->updateOrCreate(
        ['email' => 'superadmin@hcm.com'],
        [
            'name' => 'Super Admin',
            'role' => 'Super Admin',
            'password' => Hash::make('password123'),
        ],
    );

    User::query()->updateOrCreate(
        ['email' => 'manager2@hcm.com'],
        [
            'name' => 'manager2',
            'role' => 'Manager',
            'password' => Hash::make('password123'),
        ],
    );
}
}
