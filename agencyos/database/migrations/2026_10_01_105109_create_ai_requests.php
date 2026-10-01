<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('seo_tool_run_id');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->string('feature', 100);
            $t->string('provider', 30);
            $t->string('model', 100);
            $t->string('status', 30)->default('queued');
            $t->unsignedInteger('prompt_tokens')->nullable();
            $t->unsignedInteger('completion_tokens')->nullable();
            $t->unsignedInteger('total_tokens')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'seo_tool_run_id']);
            $t->foreign(['agency_id', 'seo_tool_run_id'], 'ai_run_fk')->references(['agency_id', 'id'])->on('seo_tool_runs')->restrictOnDelete();
            $t->foreign(['agency_id', 'project_id'], 'ai_project_fk')->references(['agency_id', 'id'])->on('projects')->restrictOnDelete();
            $t->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
