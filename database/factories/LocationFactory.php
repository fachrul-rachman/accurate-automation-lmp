<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Warehouse',
            'accurate_host_url' => 'https://zeus.accurate.id',
            'is_active' => true,
        ];
    }
}
