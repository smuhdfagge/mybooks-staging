<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Erases one business's data (finding O7), for a closed organisation after
 * its 30 days' notice. Only rows that belong to that business are touched:
 *
 *  - every table with a tenant_id column, WHERE tenant_id = the business;
 *  - child tables without tenant_id (invoice_items, journal_entries, ...)
 *    through their parent's id, listed in CHILD_TABLES;
 *  - rows keyed to the business's users (roles, tokens, sessions, ...);
 *  - uploaded files named in its rows (logo, photos, item images,
 *    imports, exports).
 *
 * The data_requests log is kept, as the record that the erasure happened.
 * A table without tenant_id that is in neither CHILD_TABLES nor
 * SHARED_TABLES makes the purge stop, so a new table is never skipped
 * silently.
 */
class TenantPurger
{
    /** child table => [column, parent table] */
    public const CHILD_TABLES = [
        'bill_items' => ['bill_id', 'bills'],
        'bom_items' => ['bill_of_materials_id', 'bill_of_materials'],
        'budget_lines' => ['budget_id', 'budgets'],
        'credit_note_applications' => ['credit_note_id', 'credit_notes'],
        'credit_note_items' => ['credit_note_id', 'credit_notes'],
        'delivery_note_items' => ['delivery_note_id', 'delivery_notes'],
        'employee_loan_repayments' => ['employee_loan_id', 'employee_loans'],
        'invoice_items' => ['invoice_id', 'invoices'],
        'journal_entries' => ['journal_id', 'journals'],
        'purchase_order_items' => ['purchase_order_id', 'purchase_orders'],
        'quotation_items' => ['quotation_id', 'quotations'],
        'recurrent_bill_items' => ['recurrent_bill_id', 'recurrent_bills'],
        'recurrent_invoice_items' => ['recurrent_invoice_id', 'recurrent_invoices'],
        'role_has_permissions' => ['role_id', 'roles'],
        'salary_structure_items' => ['salary_structure_id', 'salary_structures'],
        'salary_structure_versions' => ['salary_structure_id', 'salary_structures'],
        'sales_order_items' => ['sales_order_id', 'sales_orders'],
        'sales_receipt_items' => ['sales_receipt_id', 'sales_receipts'],
        'stock_transfer_items' => ['stock_transfer_id', 'stock_transfers'],
        'tax_group_rates' => ['tax_group_id', 'tax_groups'],
    ];

    /** Tables keyed to the business's users rather than to the business. */
    public const USER_TABLES = [
        'model_has_roles', 'model_has_permissions', 'personal_access_tokens',
        'notifications', 'sessions', 'idempotency_keys', 'password_reset_tokens',
    ];

    /** Tables with no business data: never touched. */
    public const SHARED_TABLES = [
        'admin_users', 'cache', 'cache_locks', 'countries', 'data_requests', 'failed_jobs',
        'job_batches', 'jobs', 'migrations', 'permissions', 'plans', 'states',
        'statutory_tax_templates', 'tenants',
    ];

    /** Uploaded files: table => [column, disk] */
    public const FILE_COLUMNS = [
        'tenants' => ['logo', 'public'],
        'employees' => ['photo_path', 'public'],
        'items' => ['image_path', 'public'],
        'imports' => ['file_path', 'imports'],
        'exports' => ['file_path', 'exports'],
    ];

    /**
     * Tables that hold rows per business (tenant_id column), except the log.
     *
     * @return array<int, string>
     */
    public function tenantTables(): array
    {
        return collect($this->allTables())
            ->reject(fn ($table) => in_array($table, self::SHARED_TABLES, true))
            ->filter(fn ($table) => Schema::hasColumn($table, 'tenant_id'))
            ->values()->all();
    }

    /**
     * Tables without tenant_id that nobody has said how to purge.
     *
     * @return array<int, string>
     */
    public function unclassifiedTables(): array
    {
        $known = array_merge(array_keys(self::CHILD_TABLES), self::USER_TABLES, self::SHARED_TABLES);

        return collect($this->allTables())
            ->reject(fn ($table) => in_array($table, $known, true))
            ->reject(fn ($table) => Schema::hasColumn($table, 'tenant_id'))
            ->values()->all();
    }

    /**
     * Erase the business. Returns rows deleted per table.
     *
     * @return array<string, int>
     */
    public function purge(Tenant $tenant): array
    {
        $unclassified = $this->unclassifiedTables();
        if ($unclassified !== []) {
            throw new \RuntimeException('Refusing to purge: add these tables to TenantPurger: '.implode(', ', $unclassified));
        }

        $tenantId = (int) $tenant->getKey();
        $files = $this->filesOf($tenantId);
        $deleted = [];

        DB::transaction(function () use ($tenantId, &$deleted) {
            $this->withoutForeignKeyChecks(function () use ($tenantId, &$deleted) {
                $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id')->all();
                $emails = DB::table('users')->where('tenant_id', $tenantId)->pluck('email')->all();

                // Children first, through their parent's tenant.
                foreach (self::CHILD_TABLES as $child => [$column, $parent]) {
                    if (Schema::hasTable($child) && Schema::hasTable($parent)) {
                        $deleted[$child] = DB::table($child)
                            ->whereIn($column, fn (Builder $q) => $q->select('id')->from($parent)->where('tenant_id', $tenantId))
                            ->delete();
                    }
                }

                $deleted += $this->purgeUserRows($userIds, $emails);

                foreach ($this->tenantTables() as $table) {
                    $deleted[$table] = DB::table($table)->where('tenant_id', $tenantId)->delete();
                }

                $deleted['tenants'] = DB::table('tenants')->where('id', $tenantId)->delete();
            });
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return array_filter($deleted);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, string>  $emails
     * @return array<string, int>
     */
    private function purgeUserRows(array $userIds, array $emails): array
    {
        $deleted = [];
        if ($userIds === []) {
            return $deleted;
        }

        $morphs = [User::class, (new User)->getMorphClass()];
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (Schema::hasTable($table)) {
                $deleted[$table] = DB::table($table)->whereIn('model_type', $morphs)->whereIn('model_id', $userIds)->delete();
            }
        }
        if (Schema::hasTable('personal_access_tokens')) {
            $deleted['personal_access_tokens'] = DB::table('personal_access_tokens')
                ->whereIn('tokenable_type', $morphs)->whereIn('tokenable_id', $userIds)->delete();
        }
        if (Schema::hasTable('notifications')) {
            $deleted['notifications'] = DB::table('notifications')
                ->whereIn('notifiable_type', $morphs)->whereIn('notifiable_id', $userIds)->delete();
        }
        foreach (['sessions', 'idempotency_keys'] as $table) {
            if (Schema::hasTable($table)) {
                $deleted[$table] = DB::table($table)->whereIn('user_id', $userIds)->delete();
            }
        }
        if (Schema::hasTable('password_reset_tokens') && $emails !== []) {
            $deleted['password_reset_tokens'] = DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
        }

        return $deleted;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function filesOf(int $tenantId): array
    {
        $files = [];
        foreach (self::FILE_COLUMNS as $table => [$column, $disk]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $key = $table === 'tenants' ? 'id' : 'tenant_id';
            foreach (DB::table($table)->where($key, $tenantId)->whereNotNull($column)->pluck($column) as $path) {
                if (is_string($path) && $path !== '' && ! str_contains($path, '..')) {
                    $files[] = [$disk, $path];
                }
            }
        }

        return $files;
    }

    /**
     * The business's rows reference each other in every direction, so
     * checks are paused (MySQL/MariaDB) or deferred to commit (SQLite)
     * while they go.
     */
    private function withoutForeignKeyChecks(callable $callback): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $callback();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        $callback();
    }

    /** @return array<int, string> */
    private function allTables(): array
    {
        // Only this database's tables, never another schema on the same server.
        return Schema::getTableListing(Schema::getCurrentSchemaName(), false);
    }
}
