<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rent relief under the Nigeria Tax Act 2025: 20% of the rent an employee
 * pays, up to 500,000 a year, comes off pay before PAYE. Payroll needs each
 * employee's annual rent to apply it (follow-up to A3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'annual_rent')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('annual_rent', 15, 2)->nullable()->after('tax_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('annual_rent');
        });
    }
};
