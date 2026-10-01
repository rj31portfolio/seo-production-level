<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('whatsapp', 40)->nullable();
            $t->string('website', 2048)->nullable();
            $t->string('industry')->nullable();
            $t->string('country')->nullable();
            $t->string('state')->nullable();
            $t->string('city')->nullable();
            $t->text('target_locations')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['agency_id', 'id']);
            $t->index(['agency_id', 'status', 'name']);
        });
        Schema::create('websites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->string('name');
            $t->string('url', 2048);
            $t->string('cms')->nullable();
            $t->string('hosting')->nullable();
            $t->string('industry')->nullable();
            $t->string('country')->nullable();
            $t->text('target_locations')->nullable();
            $t->string('status')->default('active');
            $t->string('verification_token', 64);
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['agency_id', 'id']);
            $t->index(['agency_id', 'status', 'name']);
            $t->foreign(['agency_id', 'client_id'])->references(['agency_id', 'id'])->on('clients')->restrictOnDelete();
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('website_id');
            $t->string('name');
            $t->string('type')->default('seo');
            $t->date('start_date');
            $t->text('target_locations')->nullable();
            $t->text('strategy')->nullable();
            $t->text('goals')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['agency_id', 'id']);
            $t->index(['agency_id', 'status', 'name']);
            $t->foreign(['agency_id', 'client_id'])->references(['agency_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['agency_id', 'website_id'])->references(['agency_id', 'id'])->on('websites')->restrictOnDelete();
        });
        Schema::create('project_users', function (Blueprint $t) {
            $t->unsignedBigInteger('agency_id');
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('user_id');
            $t->string('assignment_role')->default('employee');
            $t->primary(['agency_id', 'project_id', 'user_id']);
            $t->foreign(['agency_id', 'project_id'])->references(['agency_id', 'id'])->on('projects')->cascadeOnDelete();
            $t->foreign(['agency_id', 'user_id'])->references(['agency_id', 'user_id'])->on('agency_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['project_users', 'projects', 'websites', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
