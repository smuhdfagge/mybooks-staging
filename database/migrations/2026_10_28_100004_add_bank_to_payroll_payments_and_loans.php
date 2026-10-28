<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which bank paid a payroll, a payroll run or an employee loan (audit
 * MB-08). Net pay and loans always credited the payment method's default
 * account, never the bank chosen, so a business with two banks saw payroll
 * leave the wrong one. Payroll remittances use statutory_remittances.bank_id,
 * which WHT remittances already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payrolls', 'bank_id')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('payroll_batches', 'bank_id')) {
            Schema::table('payroll_batches', function (Blueprint $table) {
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('employee_loans', 'bank_id')) {
            Schema::table('employee_loans', function (Blueprint $table) {
                $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payrolls', 'payroll_batches', 'employee_loans'] as $name) {
            if (Schema::hasColumn($name, 'bank_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('bank_id');
                });
            }
        }
    }
};
