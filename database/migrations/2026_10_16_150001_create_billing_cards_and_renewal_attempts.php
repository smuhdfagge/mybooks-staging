<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session 15: Paystack auto-renewal.
 *
 * - billing_cards: the one saved card of a business (the Paystack
 *   authorization code is encrypted by the model) and its auto-renew switch;
 * - subscription_renewal_attempts: every automatic charge, one row per
 *   attempt, unique per subscription, period and attempt number so the same
 *   period can never be charged twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_cards')) {
            Schema::create('billing_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who paid when it was saved
                $table->text('authorization_code');          // encrypted
                $table->string('signature', 100)->nullable(); // Paystack's id for the card itself
                $table->string('card_type', 30)->nullable();
                $table->string('bank', 100)->nullable();
                $table->string('last4', 4);
                $table->string('exp_month', 2);
                $table->string('exp_year', 4);
                $table->string('email');                       // the email Paystack tied the card to
                $table->text('customer_code')->nullable();     // encrypted
                $table->boolean('auto_renew')->default(true);
                $table->string('expiry_warned_for', 7)->nullable(); // "2027-08" once warned
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscription_renewal_attempts')) {
            Schema::create('subscription_renewal_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
                $table->dateTime('period_end');               // the end date being renewed
                $table->unsignedTinyInteger('attempt');       // 1 = first charge, 2.. = retries
                $table->string('reference', 100)->unique();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3);
                $table->string('card_last4', 4)->nullable();
                $table->string('status', 20)->default('pending'); // pending, success, failed
                $table->string('message')->nullable();       // Paystack's reason, shown to the business
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamps();

                $table->unique(['subscription_id', 'period_end', 'attempt'], 'renewal_attempts_period_unique');
                $table->index(['tenant_id', 'created_at']);
                $table->index(['status', 'next_retry_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_attempts');
        Schema::dropIfExists('billing_cards');
    }
};
