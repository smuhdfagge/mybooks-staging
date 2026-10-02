<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase returns and supplier credit notes (vendor credits), and money
 * paid to a supplier before their bill (supplier advances).
 *
 * - vendor_credits / vendor_credit_items: the supplier's credit note,
 *   optionally linked to the bill it corrects.
 * - vendor_credit_applications: part of a credit used against a bill.
 * - vendor_credit_refunds: the supplier paying the credit back.
 * - payments_made.is_advance / unused_amount, and
 *   vendor_advance_applications: like customer deposits.
 * - A "Supplier Advances" asset account (1420) for every business.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_credits')) {
            Schema::create('vendor_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
                $table->string('vendor_credit_number', 50);
                $table->string('vendor_reference', 100)->nullable();
                $table->date('credit_date');
                $table->string('status', 20)->default('draft');
                $table->string('reason', 50)->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('balance', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('stock_returned_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'vendor_credit_number']);
                $table->index(['tenant_id', 'vendor_id', 'status']);
            });
        }

        if (! Schema::hasTable('vendor_credit_items')) {
            Schema::create('vendor_credit_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_credit_id')->constrained()->cascadeOnDelete();
                $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('description');
                $table->decimal('quantity', 15, 4)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                // What the returned goods cost us, per unit (set when they leave stock).
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_credit_applications')) {
            Schema::create('vendor_credit_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_credit_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('applied_date');
                $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'bill_id']);
            });
        }

        if (! Schema::hasTable('vendor_credit_refunds')) {
            Schema::create('vendor_credit_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_credit_id')->constrained()->cascadeOnDelete();
                $table->date('refund_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 50)->default('bank_transfer');
                $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'vendor_credit_id']);
            });
        }

        if (! Schema::hasColumn('payments_made', 'is_advance')) {
            Schema::table('payments_made', function (Blueprint $table) {
                $table->boolean('is_advance')->default(false)->after('notes');
                $table->decimal('unused_amount', 15, 2)->default(0)->after('is_advance');
            });
        }

        if (! Schema::hasTable('vendor_advance_applications')) {
            Schema::create('vendor_advance_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('advance_payment_id')->constrained('payments_made')->cascadeOnDelete();
                $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
                $table->foreignId('applied_payment_id')->nullable()->constrained('payments_made')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('application_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'vendor_id']);
            });
        }

        // The asset account advances are posted to, for existing businesses.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $exists = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('account_code', '1420')->exists();
            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'tenant_id' => $tenantId,
                    'account_code' => '1420',
                    'name' => 'Supplier Advances',
                    'type' => 'asset',
                    'sub_type' => 'other_current_asset',
                    'is_system' => true,
                    'is_active' => true,
                    'current_balance' => 0,
                    'opening_balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advance_applications');
        if (Schema::hasColumn('payments_made', 'is_advance')) {
            Schema::table('payments_made', function (Blueprint $table) {
                $table->dropColumn(['is_advance', 'unused_amount']);
            });
        }
        Schema::dropIfExists('vendor_credit_refunds');
        Schema::dropIfExists('vendor_credit_applications');
        Schema::dropIfExists('vendor_credit_items');
        Schema::dropIfExists('vendor_credits');
        // The 1420 account is left in place: it may hold postings.
    }
};
