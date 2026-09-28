<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix unique constraints on document numbers to be tenant-scoped.
     * Document numbers should be unique per tenant, not globally unique.
     */
    public function up(): void
    {
        // Sales tables
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->unique(['tenant_id', 'order_number'], 'sales_orders_tenant_order_number_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->unique(['tenant_id', 'invoice_number'], 'invoices_tenant_invoice_number_unique');
        });

        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->unique(['tenant_id', 'receipt_number'], 'sales_receipts_tenant_receipt_number_unique');
        });

        Schema::table('payments_received', function (Blueprint $table) {
            $table->dropUnique(['payment_number']);
            $table->unique(['tenant_id', 'payment_number'], 'payments_received_tenant_payment_number_unique');
        });

        // Purchase tables
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique(['bill_number']);
            $table->unique(['tenant_id', 'bill_number'], 'bills_tenant_bill_number_unique');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['expense_number']);
            $table->unique(['tenant_id', 'expense_number'], 'expenses_tenant_expense_number_unique');
        });

        Schema::table('payments_made', function (Blueprint $table) {
            $table->dropUnique(['payment_number']);
            $table->unique(['tenant_id', 'payment_number'], 'payments_made_tenant_payment_number_unique');
        });

        // HR tables
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['payroll_number']);
            $table->unique(['tenant_id', 'payroll_number'], 'payrolls_tenant_payroll_number_unique');
        });

        // Accounting tables
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique(['journal_number']);
            $table->unique(['tenant_id', 'journal_number'], 'journals_tenant_journal_number_unique');
        });

        // Fixed assets tables
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropUnique(['asset_number']);
            $table->unique(['tenant_id', 'asset_number'], 'fixed_assets_tenant_asset_number_unique');
        });

        // Invoice refunds table
        Schema::table('invoice_refunds', function (Blueprint $table) {
            $table->dropUnique(['refund_number']);
            $table->unique(['tenant_id', 'refund_number'], 'invoice_refunds_tenant_refund_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sales tables
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique('sales_orders_tenant_order_number_unique');
            $table->unique('order_number');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_invoice_number_unique');
            $table->unique('invoice_number');
        });

        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->dropUnique('sales_receipts_tenant_receipt_number_unique');
            $table->unique('receipt_number');
        });

        Schema::table('payments_received', function (Blueprint $table) {
            $table->dropUnique('payments_received_tenant_payment_number_unique');
            $table->unique('payment_number');
        });

        // Purchase tables
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_tenant_bill_number_unique');
            $table->unique('bill_number');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_tenant_expense_number_unique');
            $table->unique('expense_number');
        });

        Schema::table('payments_made', function (Blueprint $table) {
            $table->dropUnique('payments_made_tenant_payment_number_unique');
            $table->unique('payment_number');
        });

        // HR tables
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payrolls_tenant_payroll_number_unique');
            $table->unique('payroll_number');
        });

        // Accounting tables
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique('journals_tenant_journal_number_unique');
            $table->unique('journal_number');
        });

        // Fixed assets tables
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropUnique('fixed_assets_tenant_asset_number_unique');
            $table->unique('asset_number');
        });

        // Invoice refunds table
        Schema::table('invoice_refunds', function (Blueprint $table) {
            $table->dropUnique('invoice_refunds_tenant_refund_number_unique');
            $table->unique('refund_number');
        });
    }
};
