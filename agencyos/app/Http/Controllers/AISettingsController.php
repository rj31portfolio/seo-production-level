<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AI\AIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AISettingsController extends Controller
{
    public function index(): View
    {
        return view('seo.ai-settings', ['settings' => AIService::settings(), 'hasKey' => SystemSetting::whereKey('ai_key')->exists()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = ['provider' => 'required|in:deepseek', 'enabled' => 'required|boolean', 'base_url' => 'required|in:https://api.deepseek.com,https://api.deepseek.com/v1', 'model' => 'required|string|max:100|regex:/^[a-zA-Z0-9._-]+$/', 'temperature' => 'required|numeric|min:0|max:2', 'max_tokens' => 'required|integer|min:100|max:8000', 'timeout' => 'required|integer|min:5|max:120', 'api_key' => 'nullable|string|max:500', 'clear_key' => 'nullable|boolean', 'max_input_chars' => 'required|integer|min:1000|max:30000'];
        foreach (['daily_limit', 'monthly_limit', 'user_daily_limit', 'project_monthly_limit'] as $limit) {
            $rules[$limit] = 'required|integer|min:0|max:100000';
        }$data = $request->validate($rules);
        $key = $data['api_key'] ?? null;
        $clear = $data['clear_key'] ?? false;
        unset($data['api_key'],$data['clear_key']);
        DB::transaction(function () use ($data, $key, $clear): void {
            SystemSetting::updateOrCreate(['key' => 'ai_settings'], ['value' => $data]);
            if ($clear) {
                SystemSetting::whereKey('ai_key')->delete();
            } elseif ($key) {
                SystemSetting::updateOrCreate(['key' => 'ai_key'], ['value' => ['encrypted' => Crypt::encryptString($key)]]);
            }
        });

        return back()->with('success', 'AI settings saved.');
    }

    public function test(AIService $ai): RedirectResponse
    {
        try {
            $connected = $ai->provider()->test(AIService::settings(), $ai->key());

            return back()->with($connected ? 'success' : 'error', $connected ? 'Provider authentication succeeded. Model execution has not been tested.' : 'Integration unavailable. Check provider settings.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
