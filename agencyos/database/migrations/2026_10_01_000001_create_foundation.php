<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_super_admin')->default(false);
            $t->boolean('is_active')->default(true);
        });
        Schema::create('agencies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status')->default('active')->index();
            $t->string('timezone')->default('Asia/Kolkata');
            $t->string('currency', 3)->default('INR');
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('agency_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->unique(['agency_id', 'user_id']);
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->ipAddress('ip')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'agency_users', 'permission_role', 'permissions', 'roles', 'agencies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_super_admin', 'is_active']));
    }
};
