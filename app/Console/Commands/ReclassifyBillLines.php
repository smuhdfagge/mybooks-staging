<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Journal;
use App\Models\Tenant;
use App\Services\AccountCodeService;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use Illuminate\Console\Command;

/**
 * Which posted bills would move between expense accounts now that bill
 * lines post to their own account (audit MB-14). Before that, every
 * non-stock line went to Miscellaneous Expense, whatever account the line
 * named; bills are only re-posted when edited, so older ones still show
 * there.
 *
 * Dry run only: it compares each bill's posted journal with what it would
 * post today and lists the difference per account. Nothing is changed.
 * Whether and how to apply it (re-posting in place, or one reclass
 * journal dated today, which also works for closed months) is a separate
 * decision.
 */
class ReclassifyBillLines extends Command
{
    protected $signature = 'bills:reclassify-lines
                            {--dry-run : Report only (required: applying is not available yet)}
                            {--tenant= : One tenant ID only}';

    protected $description = 'Report how old bills would move from Miscellaneous Expense to their lines\' accounts (dry run)';

    public function handle(JournalService $journals): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Only a dry run is available. Run with --dry-run; nothing will be changed.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get(['id', 'name']);

        $grand = 0;
        foreach ($tenants as $tenant) {
            $grand += $this->tenant($tenant, $journals);
        }

        $this->newLine();
        $this->line("{$grand} bill(s) would be reclassified. Dry run: nothing was changed.");

        return self::SUCCESS;
    }

    protected function tenant(Tenant $tenant, JournalService $journals): int
    {
        $skip = [
            AccountCodeService::resolve($tenant->id, 'inventory'),
            AccountCodeService::resolve($tenant->id, 'input_vat'),
        ];
        $rows = [];
        $totals = [];
        $bills = 0;

        Bill::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('total', '>', 0)
            ->whereHas('items', fn ($q) => $q->whereNotNull('account_id'))
            ->orderBy('bill_date')->orderBy('id')
            ->each(function (Bill $bill) use ($journals, $skip, $tenant, &$rows, &$totals, &$bills) {
                $journal = Journal::withoutGlobalScope('tenant')
                    ->where('reference_type', Bill::class)->where('reference_id', $bill->id)
                    ->where('status', 'posted')->whereNot('reference', 'like', 'REV-%')
                    ->with('entries.account')
                    ->first();
                if (! $journal) {
                    return;
                }

                $posted = [];
                foreach ($journal->entries as $entry) {
                    $code = (string) ($entry->account->account_code ?? '');
                    if ((float) $entry->debit > 0 && ! in_array($code, $skip, true)) {
                        $posted[$code] = round(($posted[$code] ?? 0) + (float) $entry->debit, 2);
                    }
                }
                [, $expected] = $journals->billDebitSplit($bill);
                $expected = array_combine(array_map('strval', array_keys($expected)), array_values($expected));

                $changes = [];
                foreach (array_unique([...array_keys($posted), ...array_keys($expected)]) as $code) {
                    $change = round(($expected[$code] ?? 0) - ($posted[$code] ?? 0), 2);
                    if (abs($change) >= 0.005) {
                        $changes[$code] = [$posted[$code] ?? 0, $expected[$code] ?? 0, $change];
                    }
                }
                if (! $changes) {
                    return;
                }

                $bills++;
                $blocked = LockDates::instance()->blockReason($bill->bill_date, $tenant->id);
                foreach ($changes as $code => [$before, $after, $change]) {
                    $rows[] = [$bill->bill_number, $bill->bill_date->toDateString(), $code, number_format($before, 2), number_format($after, 2), number_format($change, 2), $blocked ? 'closed/locked' : 'open'];
                    $totals[$code] = round(($totals[$code] ?? 0) + $change, 2);
                }
            });

        $this->newLine();
        $this->info("Tenant {$tenant->id}: {$tenant->name} — {$bills} bill(s) to reclassify");
        if ($rows) {
            $this->table(['Bill', 'Date', 'Account', 'Posted now', 'Would post', 'Change', 'Period'], $rows);
            ksort($totals);
            $this->table(['Account', 'Net change'], array_map(fn ($code, $change) => [$code, number_format($change, 2)], array_keys($totals), $totals));
        }

        return $bills;
    }
}
