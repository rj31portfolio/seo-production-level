<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\SeoToolRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'seo_tool_run_id' => SeoToolRun::factory(), 'title' => 'Collected findings', 'snapshot' => ['agency' => 'Test agency', 'source' => 'Manual input', 'collected_at' => now()->toIso8601String(), 'summary' => [], 'rows' => [], 'missing_data' => ['No measurements supplied.']],
        ];
    }
}
