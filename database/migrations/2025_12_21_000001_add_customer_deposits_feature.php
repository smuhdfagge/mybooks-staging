<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds support for customer deposits:
     * - Customers can deposit money in advance
     * - Deposits can be applied to invoices
     * - Track deposit balance per customer
     */
    public function up(): void
    {
        // Create Customer Deposits account for each tenant
        $tenants = DB::table('tenants')->pluck('id');
        foreach ($tenants as $tenantId) {
            // Check if account doesn't already exist
            $exists = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('account_code', '2350')
                ->exists();

            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'tenant_id' => $tenantId,
                    'account_code' => '2350',
                    'name' => 'Customer Deposits',
                    'type' => 'liability',
                    'sub_type' => 'other_current_liability',
                    'description' => 'Advance payments received from customers to be applied to future invoices',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        // Add deposit tracking to payments_received table
        Schema::table('payments_received', function (Blueprint $table) {
            $table->boolean('is_deposit')->default(false)->after('notes');
            $table->decimal('unused_amount', 15, 2)->default(0)->after('is_deposit');
        });

        // Add deposit balance to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('deposit_balance', 15, 2)->default(0)->after('credit_limit');
        });

        // Create deposit applications table to track how deposits are applied to invoices
        Schema::create('customer_deposit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deposit_payment_id')->constrained('payments_received')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applied_payment_id')->nullable()->constrained('payments_received')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('application_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['deposit_payment_id']);
            $table->index(['invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_deposit_applications');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('deposit_balance');
        });

        Schema::table('payments_received', function (Blueprint $table) {
            $table->dropColumn(['is_deposit', 'unused_amount']);
        });
    }
};
