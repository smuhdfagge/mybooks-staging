<?php

namespace App\Actions\AccrualSchedules;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\AccrualSchedule;
use App\Models\AccrualScheduleRelease;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Release every month of a schedule whose last day has come by $asOf
 * (today by default), one posted journal per month (S9):
 *   prepaid expense:  Dr the expense,      Cr Prepaid Expenses
 *   deferred revenue: Dr Deferred Revenue, Cr the income
 * A month due in a closed or locked period is posted on the first open
 * date, and the journal says so (same rule as automatic reversals). Each
 * month is released once only: the schedule row is locked and re-read,
 * and (schedule, month) is unique, so running this again does nothing.
 */
class ReleaseAccrualSchedule
{
    public function __construct(protected JournalService $journals) {}

    /** @return int months released */
    public function handle(AccrualSchedule $schedule, ?CarbonInterface $asOf = null): int
    {
        if (! EnsureFeatureEnabled::enabled('prepaid_schedules')) {
            return 0;
        }
        $asOf = ($asOf ?? today())->copy()->startOfDay();

        return DB::transaction(function () use ($schedule, $asOf) {
            $schedule = AccrualSchedule::withoutGlobalScope('tenant')->lockForUpdate()->find($schedule->id);
            if (! $schedule || ! $schedule->isActive()) {
                return 0;
            }

            $done = AccrualScheduleRelease::withoutGlobalScope('tenant')
                ->where('accrual_schedule_id', $schedule->id)->pluck('sequence')->all();
            $balance = ChartOfAccount::withoutGlobalScope('tenant')->findOrFail($schedule->balance_account_id);
            $pl = ChartOfAccount::withoutGlobalScope('tenant')->findOrFail($schedule->pl_account_id);
            $count = 0;

            foreach ($schedule->monthlyAmounts() as $sequence => $amount) {
                if (in_array($sequence, $done, true)) {
                    continue;
                }
                $due = $schedule->dueDate($sequence);
                if ($due->gt($asOf)) {
                    break;
                }
                $date = $this->journals->firstOpenDate($schedule->tenant_id, $due);
                if ($date->gt($asOf)) {
                    break; // the next open date hasn't come yet; try again then
                }
                $note = LockDates::instance()->movedNote($schedule->tenant_id, $due, $date);

                $label = "{$schedule->description} - month {$sequence} of {$schedule->months} ({$schedule->schedule_number})";
                $lines = $schedule->isPrepaid()
                    ? [[$pl->account_code, $amount, 0.0, $label], [$balance->account_code, 0.0, $amount, $label]]
                    : [[$balance->account_code, $amount, 0.0, $label], [$pl->account_code, 0.0, $amount, $label]];
                $what = $schedule->isPrepaid() ? 'Prepaid expense released' : 'Deferred revenue earned';

                $journal = $this->journals->postLines($schedule, $schedule->schedule_number, $date->toDateString(),
                    "{$what}: {$label}".($note ? " ({$note})" : ''), $lines, $schedule->created_by, Journal::TYPE_SCHEDULE_RELEASE);

                $release = new AccrualScheduleRelease([
                    'tenant_id' => $schedule->tenant_id,
                    'accrual_schedule_id' => $schedule->id,
                    'sequence' => $sequence,
                    'due_date' => $due->toDateString(),
                    'posted_date' => $date->toDateString(),
                    'amount' => $amount,
                    'journal_id' => $journal->id,
                    'note' => $note ? ucfirst($note) : null,
                ]);
                $release->skipTenantGuard = true;
                $release->save();

                $schedule->released_amount = Money::add($schedule->released_amount, $amount);
                $count++;
            }

            if (count($done) + $count >= $schedule->months) {
                $schedule->status = AccrualSchedule::STATUS_COMPLETED;
            }
            $schedule->save();

            return $count;
        });
    }
}
