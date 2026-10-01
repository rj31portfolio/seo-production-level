<?php

namespace Database\Factories;

use App\Models\SeoMonitoring;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMonitoring>
 */
class SeoMonitoringFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'interval_minutes' => 1440, 'enabled' => true, 'next_due_at' => now(),
        ];
    }
}
