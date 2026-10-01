<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_tool_plans', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->json('tools');
            $t->json('limits');
            $t->unsignedInteger('max_pages')->default(30);
            $t->unsignedInteger('monthly_pages')->nullable();
            $t->unsignedInteger('ai_daily_limit')->nullable();
            $t->unsignedInteger('ai_monthly_limit')->nullable();
            $t->timestamps();
        });
        Schema::create('agency_tool_plan', function (Blueprint $t): void {
            $t->foreignId('agency_id')->primary()->constrained()->restrictOnDelete();
            $t->foreignId('saas_tool_plan_id')->constrained('saas_tool_plans')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_tool_plan');
        Schema::dropIfExists('saas_tool_plans');
    }
};
