<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbers are generated per tenant (PO-000001, PBN-000001, ...) but
 * these tables had a global unique index, so the second tenant to create a
 * document crashed. Make them unique per tenant, like the other documents
 * (see 2026_01_24_092538_fix_document_number_unique_constraints_per_tenant).
 *
 * Findings H3 and N3.
 */
return new class extends Migration
{
    private array $columns = [
        'payroll_batches' => 'batch_number',
        'purchase_orders' => 'order_number',
        'quotations' => 'quotation_number',
        'delivery_notes' => 'delivery_number',
        'credit_notes' => 'credit_note_number',
        'employee_loans' => 'loan_number',
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column) {
                $t->dropUnique([$column]);
                $t->unique(['tenant_id', $column], "{$table}_tenant_{$column}_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column) {
                $t->dropUnique("{$table}_tenant_{$column}_unique");
                $t->unique([$column]);
            });
        }
    }
};
