<?php

namespace Database\Factories;

use App\Models\SeoTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTask>
 */
class SeoTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(), 'title' => 'Review collected finding', 'description' => 'Review the supplied test finding.', 'category' => 'technical', 'priority' => 'medium', 'status' => 'pending', 'due_at' => now()->addDays(2), 'deduplication_key' => hash('sha256', fake()->uuid()),
        ];
    }
}
