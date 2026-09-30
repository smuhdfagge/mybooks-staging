<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for reports, balances and mobile sync (P8). Each one is only
 * added when it is missing, so this can be run again safely.
 */
return new class extends Migration
{
    /** @return array<string, array<string, array<int, string>>> table => [index name => columns] */
    private function indexes(): array
    {
        $sync = fn (string $table) => [$table.'_tenant_updated_idx' => ['tenant_id', 'updated_at']];

        return [
            'journals' => ['journals_tenant_posted_date_idx' => ['tenant_id', 'is_posted', 'journal_date']],
            'journal_entries' => ['journal_entries_account_journal_idx' => ['account_id', 'journal_id']],
            'invoices' => [
                'invoices_tenant_due_idx' => ['tenant_id', 'due_date'],
                'invoices_customer_status_idx' => ['customer_id', 'status'],
            ] + $sync('invoices'),
            'bills' => [
                'bills_tenant_bill_date_idx' => ['tenant_id', 'bill_date'],
                'bills_tenant_due_idx' => ['tenant_id', 'due_date'],
                'bills_vendor_status_idx' => ['vendor_id', 'status'],
            ] + $sync('bills'),
            'expenses' => [
                'expenses_tenant_status_date_idx' => ['tenant_id', 'status', 'expense_date'],
            ] + $sync('expenses'),
            'customers' => $sync('customers'),
            'vendors' => $sync('vendors'),
            'items' => $sync('items'),
            'payments_received' => $sync('payments_received'),
            'payments_made' => $sync('payments_made'),
            'chart_of_accounts' => $sync('chart_of_accounts'),
            'tax_rates' => $sync('tax_rates'),
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // MySQL/MariaDB drop the index they made for a foreign key once a
            // wider index can serve it, so give each such key its own index
            // back before removing the wider one.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $foreignColumns = collect(Schema::getForeignKeys($table))
                    ->map(fn ($fk) => $fk['columns'][0] ?? null)->filter()->all();
                foreach ($indexes as $columns) {
                    if (in_array($columns[0], $foreignColumns, true) && ! Schema::hasIndex($table, [$columns[0]])) {
                        Schema::table($table, fn (Blueprint $t) => $t->index([$columns[0]]));
                    }
                }
            }

            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
