<?php

namespace Database\Factories;

use App\Models\Backlink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backlink>
 */
class BacklinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'source_url' => 'https://source.example.com/', 'target_url' => 'https://example.com/', 'source' => 'Manual', 'status' => 'unverified', 'url_pair_hash' => fn (array $attributes) => hash('sha256', $attributes['source_url'].'|'.$attributes['target_url']),
        ];
    }
}
