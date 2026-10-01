<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_tool_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('website_id')->nullable();
            $t->string('tool', 80);
            $t->string('status', 30)->default('queued');
            $t->string('source', 100);
            $t->json('input');
            $t->json('summary')->nullable();
            $t->string('error', 500)->nullable();
            $t->unsignedInteger('processed')->default(0);
            $t->unsignedInteger('discovered')->default(0);
            $t->dateTime('started_at')->nullable();
            $t->dateTime('finished_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['agency_id','id']);
            $t->index(['agency_id','tool','created_at'],'tool_run_history_index');
            $t->index(['agency_id','project_id','status'],'tool_run_project_index');
            $t->foreign(['agency_id','client_id'],'tool_run_client_fk')->references(['agency_id','id'])->on('clients')->restrictOnDelete();
            $t->foreign(['agency_id','project_id'],'tool_run_project_fk')->references(['agency_id','id'])->on('projects')->restrictOnDelete();
            $t->foreign(['agency_id','website_id'],'tool_run_website_fk')->references(['agency_id','id'])->on('websites')->restrictOnDelete();
        });
        Schema::create('seo_tool_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('seo_tool_run_id');
            $t->string('url',2048)->nullable();
            $t->string('kind',50)->default('analysis');
            $t->json('data');
            $t->timestamps();
            $t->unique(['agency_id','id']);
            $t->index(['agency_id','seo_tool_run_id','kind'],'tool_result_run_index');
            $t->foreign(['agency_id','seo_tool_run_id'],'tool_result_run_fk')->references(['agency_id','id'])->on('seo_tool_runs')->restrictOnDelete();
        });
        Schema::create('tool_limits',function (Blueprint $t) {
            $t->id(); $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->string('tool',80); $t->unsignedInteger('monthly_runs')->nullable();
            $t->boolean('enabled')->default(true); $t->timestamps(); $t->unique(['agency_id','tool']);
        });
        Schema::create('seo_tool_usage',function (Blueprint $t) {
            $t->id(); $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->string('tool',80); $t->string('period',7); $t->unsignedInteger('runs')->default(0);
            $t->timestamps(); $t->unique(['agency_id','tool','period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['seo_tool_usage','tool_limits','seo_tool_results','seo_tool_runs'] as $table) { Schema::dropIfExists($table); }
    }
};
