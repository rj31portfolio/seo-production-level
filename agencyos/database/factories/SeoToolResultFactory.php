<?php

namespace Database\Factories;

use App\Models\SeoToolResult;
use App\Models\SeoToolRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoToolResult>
 */
class SeoToolResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_tool_run_id' => SeoToolRun::factory(), 'kind' => 'analysis', 'data' => [],
        ];
    }
}
