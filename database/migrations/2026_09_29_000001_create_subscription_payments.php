<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finding C1: subscriptions are paid for through Paystack.
 *
 * - subscriptions.status gains 'pending' (signed up, not yet paid);
 * - subscription_payments records each checkout and what Paystack said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'cancelled', 'expired', 'past_due', 'trialing'])
                ->default('pending')->change();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_cycle', 20);
            $table->string('reference', 100)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('gateway', 30)->default('paystack');
            $table->string('status', 20)->default('pending'); // pending, success, failed
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'cancelled', 'expired', 'past_due', 'trialing'])
                ->default('active')->change();
        });
    }
};
