<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Location;
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
            ['email' => 'admin@x.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        Location::query()->updateOrCreate(
            ['name' => 'Karawang'],
            [
                'accurate_host_url' => 'https://zeus.accurate.id',
                'is_active' => true,
            ],
        );

        Location::query()->updateOrCreate(
            ['name' => 'Tangerang'],
            [
                'accurate_host_url' => 'https://iris.accurate.id',
                'is_active' => true,
            ],
        );
    }
}
