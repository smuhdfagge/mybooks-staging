<?php

namespace App\Console\Commands;

use App\Models\AccrualSchedule;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\AccountCodeService;
use App\Services\Accounting\FinancialStatements;
use App\Support\PaymentMethods;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report of data the October 2026 audit says may already be
 * wrong, so it can be checked by hand before the real fixes land:
 *
 * - prepaid/deferred schedules linked to a bill, expense or invoice with
 *   no setup journal (MB-53: the amount was counted twice; repair with
 *   accruals:repair-setup);
 * - Prepaid Expenses or Deferred Revenue below zero (the same cause);
 * - custom roles holding user, role or report permissions (MB-05, MB-06);
 * - banks whose balance differs from their ledger account (MB-08);
 * - payments, receipts and expenses whose payment method is not on the
 *   list (MB-03: it was free text and could hold script).
 *
 * Nothing is changed.
 */
class AuditAffectedData extends Command
{
    protected $signature = 'mybooks:audit-affected-data {--tenant= : Check one tenant ID only}';

    protected $description = 'List data that may be affected by the October 2026 audit findings (read-only)';

    /** Permissions a custom role should be reviewed for. */
    public const SENSITIVE_PERMISSIONS = ['edit users', 'edit roles', 'export reports', 'view reports'];

    public function handle(FinancialStatements $statements): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $flagged = 0;
        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("Tenant {$tenant->id}: {$tenant->name}");
            $ledger = Tenant::run($tenant, fn () => $statements->accountBalances($tenant->id, null, '2999-12-31')->keyBy('id'));
            $flagged += Tenant::run($tenant, fn () => $this->linkedSchedules($tenant->id)
                + $this->negativeBalances($tenant->id, $ledger)
                + $this->sensitiveRoles($tenant->id)
                + $this->bankDifferences($tenant->id, $ledger)
                + $this->unknownPaymentMethods($tenant->id));
        }

        $this->newLine();
        $this->line("{$flagged} item(s) to review across {$tenants->count()} tenant(s). Nothing was changed.");

        return self::SUCCESS;
    }

    protected function linkedSchedules(int $tenantId): int
    {
        $rows = AccrualSchedule::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('source_type')
            ->whereNull('setup_journal_id') // linked before the setup journal existed (MB-53); see accruals:repair-setup
            ->orderBy('id')
            ->get(['schedule_number', 'type', 'status', 'source_type', 'source_id', 'total_amount', 'released_amount'])
            ->map(fn ($s) => [$s->schedule_number, $s->type, $s->status, "{$s->source_type}:{$s->source_id}", $s->total_amount, $s->released_amount]);

        return $this->section('Schedules linked to a document without a setup journal', ['Schedule', 'Type', 'Status', 'Linked to', 'Total', 'Released'], $rows->all());
    }

    /** @param Collection<int, ChartOfAccount> $ledger */
    protected function negativeBalances(int $tenantId, Collection $ledger): int
    {
        $codes = [
            AccountCodeService::resolve($tenantId, 'prepaid_expenses'),
            AccountCodeService::resolve($tenantId, 'deferred_revenue'),
        ];
        $rows = $ledger
            ->filter(fn ($a) => in_array($a->account_code, $codes, true) && $a->balance <= -0.005)
            ->map(fn ($a) => [$a->account_code, $a->name, number_format($a->balance, 2)]);

        return $this->section('Prepaid Expenses / Deferred Revenue below zero', ['Code', 'Account', 'Ledger balance'], $rows->values()->all());
    }

    protected function sensitiveRoles(int $tenantId): int
    {
        $rows = Role::query()
            ->where('tenant_id', $tenantId)
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [$role->name, $role->permissions->pluck('name')->intersect(self::SENSITIVE_PERMISSIONS)->sort()->implode(', ')])
            ->filter(fn ($row) => $row[1] !== '');

        return $this->section('Custom roles with user, role or report permissions', ['Role', 'Permissions'], $rows->values()->all());
    }

    /** @param Collection<int, ChartOfAccount> $ledger */
    protected function bankDifferences(int $tenantId, Collection $ledger): int
    {
        $rows = Bank::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'name', 'chart_of_account_id', 'current_balance'])
            ->map(function ($bank) use ($ledger) {
                $account = $ledger->get($bank->chart_of_account_id);
                $balance = $account ? (float) $account->balance : null;

                return [$bank->name, $account->account_code ?? 'none', number_format((float) $bank->current_balance, 2),
                    $balance === null ? 'n/a' : number_format($balance, 2), $balance === null ? null : round((float) $bank->current_balance - $balance, 2)];
            })
            ->filter(fn ($row) => $row[4] === null || abs($row[4]) >= 0.005)
            ->map(fn ($row) => [...array_slice($row, 0, 4), $row[4] === null ? 'no ledger account' : number_format($row[4], 2)]);

        return $this->section('Banks whose balance differs from the ledger', ['Bank', 'Account', 'Bank balance', 'Ledger balance', 'Difference'], $rows->values()->all());
    }

    protected function unknownPaymentMethods(int $tenantId): int
    {
        $allowed = [...PaymentMethods::selectable(), ...PaymentMethods::INTERNAL];
        $rows = [];
        foreach (['payments_received', 'payments_made', 'sales_receipts', 'expenses'] as $table) {
            DB::table($table)
                ->where('tenant_id', $tenantId)
                ->whereNotNull('payment_method')
                ->whereNotIn('payment_method', $allowed)
                ->selectRaw('payment_method, count(*) as n')
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get()
                // The value itself may be hostile; show it as JSON so it can't do anything in a terminal.
                ->each(function ($row) use ($table, &$rows) {
                    $rows[] = [$table, json_encode(mb_strimwidth($row->payment_method, 0, 60, '…')), $row->n];
                });
        }

        return $this->section('Payment methods not on the list', ['Table', 'Value', 'Rows'], $rows);
    }

    /** @param array<int, array<int, mixed>> $rows */
    protected function section(string $title, array $headers, array $rows): int
    {
        $this->line("  {$title}: ".(count($rows) ?: 'none'));
        if ($rows) {
            $this->table($headers, $rows);
        }

        return count($rows);
    }
}
