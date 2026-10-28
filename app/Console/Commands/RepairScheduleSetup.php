<?php

namespace App\Console\Commands;

use App\Actions\AccrualSchedules\ScheduleSetup;
use App\Models\AccrualSchedule;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prepaid / deferred schedules linked to a bill, expense or invoice before
 * the setup journal existed (audit MB-53) released their months out of an
 * account the money was never put in, so the expense or income was counted
 * twice. This lists them with the setup journal each needs (the full total,
 * dated the document's date or the first open date after it).
 *
 * Without --apply it only reports. --apply posts the journals; run it only
 * once the business owner has agreed.
 */
class RepairScheduleSetup extends Command
{
    protected $signature = 'accruals:repair-setup
                            {--apply : Post the setup journals (needs the owner\'s go-ahead)}
                            {--tenant= : One tenant ID only}';

    protected $description = 'List (or with --apply, post) the setup journals missing from linked prepaid/deferred schedules';

    public function handle(ScheduleSetup $setup): int
    {
        $apply = (bool) $this->option('apply');
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')->get(['id', 'name']);

        $found = 0;
        $posted = 0;
        foreach ($tenants as $tenant) {
            $rows = [];
            $schedules = AccrualSchedule::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('source_type')->whereNull('setup_journal_id')
                ->with(['balanceAccount' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->orderBy('id')->get();

            foreach ($schedules as $schedule) {
                try {
                    $from = $setup->sourceAccount($schedule);
                } catch (ValidationException $e) {
                    $rows[] = [$schedule->schedule_number, $schedule->typeLabel(), $schedule->source_type, '-', number_format((float) $schedule->total_amount, 2), 'cannot: '.collect($e->errors())->flatten()->first()];

                    continue;
                }
                if ($from === null) {
                    continue; // the document already posted to the balance account
                }
                $found++;
                $direction = $schedule->isPrepaid() ? "{$from} -> {$schedule->balanceAccount?->account_code}" : "{$schedule->balanceAccount?->account_code} <- {$from}";
                $status = 'to post';
                if ($apply) {
                    $journal = DB::transaction(fn () => $setup->post($schedule));
                    $status = $journal ? "posted {$journal->journal_number}" : 'nothing needed';
                    $posted += $journal ? 1 : 0;
                }
                $rows[] = [$schedule->schedule_number, $schedule->typeLabel(), $schedule->source_type, $direction, number_format((float) $schedule->total_amount, 2), $status];
            }

            if ($rows) {
                $this->newLine();
                $this->info("Tenant {$tenant->id}: {$tenant->name}");
                $this->table(['Schedule', 'Type', 'Linked to', 'Moves (account codes)', 'Amount', 'Status'], $rows);
            }
        }

        $this->newLine();
        $this->line($apply
            ? "{$posted} setup journal(s) posted."
            : "{$found} schedule(s) need a setup journal. Dry run: nothing was changed. Run again with --apply once the owner agrees.");

        return self::SUCCESS;
    }
}
