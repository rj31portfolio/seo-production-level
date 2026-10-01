<?php

namespace Database\Factories;

use App\Models\RankingEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RankingEntry>
 */
class RankingEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'observed_on' => now()->toDateString(), 'position' => null, 'country' => 'IN', 'location' => '', 'device' => 'desktop', 'search_engine' => 'google', 'source' => 'Manual',
        ];
    }
}
