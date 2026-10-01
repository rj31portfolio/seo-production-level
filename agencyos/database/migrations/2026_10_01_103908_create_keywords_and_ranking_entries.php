<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->string('keyword', 200);
            $t->string('target_url', 2048)->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'id']);
            $t->unique(['agency_id', 'project_id', 'keyword']);
            $t->foreign(['agency_id', 'project_id'])->references(['agency_id', 'id'])->on('projects')->restrictOnDelete();
        });
        Schema::create('ranking_entries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('keyword_id');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->date('observed_on');
            $t->unsignedSmallInteger('position')->nullable();
            $t->string('country', 2);
            $t->string('location', 100)->default('');
            $t->string('device', 20);
            $t->string('search_engine', 50);
            $t->string('source', 30);
            $t->string('evidence', 2048)->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'id']);
            $t->unique(['agency_id', 'keyword_id', 'observed_on', 'country', 'location', 'device', 'search_engine'], 'ranking_observation_unique');
            $t->foreign(['agency_id', 'keyword_id'])->references(['agency_id', 'id'])->on('keywords')->restrictOnDelete();
            $t->index(['agency_id', 'keyword_id', 'observed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_entries');
        Schema::dropIfExists('keywords');
    }
};
