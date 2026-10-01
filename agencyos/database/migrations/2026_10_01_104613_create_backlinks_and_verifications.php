<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backlinks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('source_url', 2048);
            $t->string('target_url', 2048);
            $t->string('anchor', 300)->nullable();
            $t->string('campaign', 150)->nullable();
            $t->string('source', 30);
            $t->string('status', 30)->default('unverified');
            $t->string('url_pair_hash', 64);
            $t->timestamps();
            $t->unique(['agency_id', 'id']);
            $t->unique(['agency_id', 'project_id', 'url_pair_hash'], 'backlink_pair_unique');
            $t->foreign(['agency_id', 'project_id'])->references(['agency_id', 'id'])->on('projects')->restrictOnDelete();
        });
        Schema::create('backlink_verifications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('backlink_id');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('queued');
            $t->json('data')->nullable();
            $t->string('error', 500)->nullable();
            $t->dateTime('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'id']);
            $t->foreign(['agency_id', 'backlink_id'])->references(['agency_id', 'id'])->on('backlinks')->restrictOnDelete();
            $t->index(['agency_id', 'backlink_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backlink_verifications');
        Schema::dropIfExists('backlinks');
    }
};
