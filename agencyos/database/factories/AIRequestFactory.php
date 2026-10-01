<?php

namespace Database\Factories;

use App\Models\AIRequest;
use App\Models\SeoToolRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AIRequest>
 */
class AIRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'seo_tool_run_id' => SeoToolRun::factory(), 'feature' => 'ai-assistant', 'provider' => 'deepseek', 'model' => 'deepseek-flash', 'status' => 'queued',
        ];
    }
}
