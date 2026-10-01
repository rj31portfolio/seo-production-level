<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('client_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->unsignedBigInteger('price_minor');
            $t->string('currency', 3);
            $t->unsignedInteger('duration_days');
            $t->string('billing_cycle');
            $t->json('limits')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['agency_id', 'id']);
            $t->index(['agency_id', 'is_active']);
        });
        Schema::create('client_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('client_plan_id');
            $t->dateTime('starts_at');
            $t->dateTime('expires_at');
            $t->unsignedInteger('grace_days')->default(0);
            $t->string('expiry_mode')->default('read_only');
            $t->string('status')->default('active');
            $t->boolean('auto_renew')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'client_id']);
            $t->unique(['agency_id', 'id']);
            $t->index(['agency_id', 'expires_at']);
            $t->foreign(['agency_id', 'client_id'])->references(['agency_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['agency_id', 'client_plan_id'])->references(['agency_id', 'id'])->on('client_plans')->restrictOnDelete();
        });
        Schema::create('client_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('client_subscription_id');
            $t->string('number');
            $t->string('description');
            $t->string('currency', 3);
            $t->unsignedBigInteger('amount_minor');
            $t->string('status')->default('unpaid');
            $t->date('due_date');
            $t->timestamps();
            $t->unique(['agency_id', 'number']);
            $t->unique(['agency_id', 'id']);
            $t->foreign(['agency_id', 'client_id'])->references(['agency_id', 'id'])->on('clients')->restrictOnDelete();
            $t->foreign(['agency_id', 'client_subscription_id'])->references(['agency_id', 'id'])->on('client_subscriptions')->restrictOnDelete();
        });
        Schema::create('client_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_invoice_id');
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('method');
            $t->string('reference');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->dateTime('paid_at');
            $t->timestamps();
            $t->unique(['agency_id', 'reference']);
            $t->foreign(['agency_id', 'client_invoice_id'])->references(['agency_id', 'id'])->on('client_invoices')->restrictOnDelete();
        });
        Schema::create('client_subscription_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_subscription_id');
            $t->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after');
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
            $t->foreign(['agency_id', 'client_subscription_id'], 'client_history_subscription_fk')->references(['agency_id', 'id'])->on('client_subscriptions')->restrictOnDelete();
            $t->index(['agency_id', 'client_subscription_id', 'created_at'], 'client_history_subscription_date_index');
        });
        Schema::create('renewal_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('client_subscription_id');
            $t->dateTime('expiry');
            $t->unsignedInteger('threshold');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['agency_id', 'client_subscription_id', 'expiry', 'threshold'], 'reminder_period_unique');
            $t->foreign(['agency_id', 'client_subscription_id'])->references(['agency_id', 'id'])->on('client_subscriptions')->restrictOnDelete();
        });
        Schema::create('in_app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('message');
            $t->string('url');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['in_app_notifications', 'renewal_reminders', 'client_subscription_history', 'client_payments', 'client_invoices', 'client_subscriptions', 'client_plans', 'system_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
