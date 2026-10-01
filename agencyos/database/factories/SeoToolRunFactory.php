<?php

namespace Database\Factories;

use App\Models\SeoToolRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoToolRun>
 */
class SeoToolRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'tool' => 'page-score', 'status' => 'queued', 'source' => 'Internal crawler', 'input' => ['url' => 'https://example.com/'],
        ];
    }
}
