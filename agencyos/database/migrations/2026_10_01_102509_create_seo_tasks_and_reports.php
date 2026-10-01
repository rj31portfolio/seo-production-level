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
        Schema::create('seo_tasks',function (Blueprint $t) {
            $t->id();$t->foreignId('agency_id')->constrained()->restrictOnDelete();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('client_id');$t->unsignedBigInteger('website_id');$t->unsignedBigInteger('seo_tool_run_id');$t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->string('title');$t->text('description');$t->string('category',40);$t->string('priority',20);$t->string('status',30)->default('pending');$t->dateTime('due_at');$t->dateTime('completed_at')->nullable();$t->string('deduplication_key',64);$t->text('completion_note')->nullable();$t->timestamps();$t->unique(['agency_id','deduplication_key']);$t->index(['agency_id','project_id','status']);
            $t->foreign(['agency_id','project_id'],'seo_task_project_fk')->references(['agency_id','id'])->on('projects')->restrictOnDelete();
            $t->foreign(['agency_id','client_id'],'seo_task_client_fk')->references(['agency_id','id'])->on('clients')->restrictOnDelete();
            $t->foreign(['agency_id','website_id'],'seo_task_website_fk')->references(['agency_id','id'])->on('websites')->restrictOnDelete();
            $t->foreign(['agency_id','seo_tool_run_id'],'seo_task_run_fk')->references(['agency_id','id'])->on('seo_tool_runs')->restrictOnDelete();
        });
        Schema::create('reports',function (Blueprint $t) {
            $t->id();$t->foreignId('agency_id')->constrained()->restrictOnDelete();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->unsignedBigInteger('project_id')->nullable();$t->unsignedBigInteger('seo_tool_run_id');$t->string('title');$t->json('snapshot');$t->timestamps();$t->index(['agency_id','created_at']);
            $t->foreign(['agency_id','project_id'],'report_project_fk')->references(['agency_id','id'])->on('projects')->restrictOnDelete();
            $t->foreign(['agency_id','seo_tool_run_id'],'report_run_fk')->references(['agency_id','id'])->on('seo_tool_runs')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');Schema::dropIfExists('seo_tasks');
    }
};
