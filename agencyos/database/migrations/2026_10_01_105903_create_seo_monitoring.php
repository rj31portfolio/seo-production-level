<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_monitoring', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('pending_run_id')->nullable();
            $t->unsignedBigInteger('last_run_id')->nullable();
            $t->unsignedInteger('interval_minutes');
            $t->boolean('enabled')->default(true);
            $t->dateTime('next_due_at');
            $t->dateTime('last_checked_at')->nullable();
            $t->string('last_error', 500)->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'project_id']);
            $t->foreign(['agency_id', 'project_id'], 'monitor_project_fk')->references(['agency_id', 'id'])->on('projects')->restrictOnDelete();
            $t->foreign(['agency_id', 'pending_run_id'], 'monitor_pending_fk')->references(['agency_id', 'id'])->on('seo_tool_runs')->restrictOnDelete();
            $t->foreign(['agency_id', 'last_run_id'], 'monitor_last_fk')->references(['agency_id', 'id'])->on('seo_tool_runs')->restrictOnDelete();
            $t->index(['agency_id', 'enabled', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_monitoring');
    }
};
