<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\SaasToolPlan;
use App\Services\Seo\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToolPlanController extends Controller
{
    public function index(): View
    {
        return view('seo.tool-plans', ['plans' => SaasToolPlan::orderBy('name')->get(), 'tools' => ToolRegistry::all(), 'agencies' => Agency::orderBy('name')->get(['id', 'name']), 'assignments' => DB::table('agency_tool_plan')->join('agencies', 'agencies.id', '=', 'agency_tool_plan.agency_id')->join('saas_tool_plans', 'saas_tool_plans.id', '=', 'agency_tool_plan.saas_tool_plan_id')->select('agencies.name as agency', 'saas_tool_plans.name as plan')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan_id' => 'nullable|integer|exists:saas_tool_plans,id', 'name' => 'required|string|max:150', 'tools' => 'nullable|array', 'tools.*' => Rule::in(array_keys(ToolRegistry::all())), 'limits' => ['nullable', 'array:'.implode(',', array_keys(ToolRegistry::all()))], 'limits.*' => 'nullable|integer|min:0|max:1000000', 'max_pages' => 'required|integer|min:1|max:100', 'monthly_pages' => 'nullable|integer|min:0|max:10000000', 'ai_daily_limit' => 'nullable|integer|min:0|max:100000', 'ai_monthly_limit' => 'nullable|integer|min:0|max:1000000']);
        $id = $data['plan_id'] ?? null;
        unset($data['plan_id']);
        $data['tools'] = array_values($data['tools'] ?? []);
        $data['limits'] = array_filter($data['limits'] ?? [], fn ($limit) => $limit !== null);
        $id ? SaasToolPlan::findOrFail($id)->update($data) : SaasToolPlan::create($data);

        return back()->with('success', 'Platform tool plan saved.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate(['agency_id' => 'required|integer|exists:agencies,id', 'saas_tool_plan_id' => 'nullable|integer|exists:saas_tool_plans,id']);
        if (empty($data['saas_tool_plan_id'])) {
            DB::table('agency_tool_plan')->where('agency_id', $data['agency_id'])->delete();
        } else {
            DB::table('agency_tool_plan')->updateOrInsert(['agency_id' => $data['agency_id']], ['saas_tool_plan_id' => $data['saas_tool_plan_id'], 'updated_at' => now(), 'created_at' => now()]);
        }

return back()->with('success','Agency tool plan assignment updated.');
    }
}
