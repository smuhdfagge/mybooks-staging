<?php

namespace App\Actions\Accruals;

use App\Models\AccrualSchedule;
use App\Models\AccrualScheduleRelease;
use App\Models\Journal;
use App\Services\Accounting\PostingLock;
use App\Services\JournalService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Release every month of a schedule that is due by $asOf (today by
 * default), one journal per month:
 *   prepaid expense:  Dr the expense,          Cr Prepaid
 *   deferred revenue: Dr Deferred revenue,     Cr the income
 * A month due in a closed or locked period is posted on the first open
 * date, with a note saying so. Each month is released once only (unique
 * schedule + month), so running this again does nothing more.
 */
class ReleaseAccrualSchedule
{
    public function __construct(protected JournalService $journals) {}

    /** @return int months released */
    public function handle(AccrualSchedule $schedule, ?CarbonInterface $asOf = null): int
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $count = 0;

        DB::transaction(function () use ($schedule, $asOf, &$count) {
            $schedule = AccrualSchedule::withoutGlobalScopes()->lockForUpdate()->find($schedule->id);
            if (! $schedule || $schedule->status !== AccrualSchedule::STATUS_ACTIVE) {
                return;
            }

            $done = $schedule->releases()->withoutGlobalScopes()->pluck('sequence')->all();
            $balance = $schedule->balanceAccount()->withoutGlobalScopes()->first();
            $pl = $schedule->plAccount()->withoutGlobalScopes()->first();

            foreach ($schedule->monthlyAmounts() as $sequence => $amount) {
                if (in_array($sequence, $done, true)) {
                    continue;
                }
                $due = $schedule->dueDate($sequence);
                if ($due->greaterThan($asOf)) {
                    break;
                }

                $date = PostingLock::firstOpenDate($schedule->tenant_id, $due);
                $note = $date->equalTo($due) ? null
                    : "Due {$due->format('d M Y')}, but the books are closed or locked then, so posted on {$date->format('d M Y')}.";
                $label = "{$schedule->description} - month {$sequence} of {$schedule->months} ({$schedule->schedule_number})";
                $lines = $schedule->isPrepaid()
                    ? [[$pl->account_code, $amount, 0.0, $label], [$balance->account_code, 0.0, $amount, $label]]
                    : [[$balance->account_code, $amount, 0.0, $label], [$pl->account_code, 0.0, $amount, $label]];

                $journal = $this->journals->postLines($schedule, $schedule->schedule_number, $date->toDateString(),
                    ($schedule->isPrepaid() ? 'Prepaid expense released: ' : 'Income earned: ').$label.($note ? " - {$note}" : ''),
                    $lines, $schedule->created_by, Journal::TYPE_SCHEDULE_RELEASE);

                AccrualScheduleRelease::create([
                    'tenant_id' => $schedule->tenant_id,
                    'accrual_schedule_id' => $schedule->id,
                    'sequence' => $sequence,
                    'due_date' => $due->toDateString(),
                    'posted_date' => $date->toDateString(),
                    'amount' => $amount,
                    'journal_id' => $journal->id,
                    'note' => $note,
                ]);

                $schedule->released_amount = Money::add($schedule->released_amount, $amount);
                $count++;
            }

            if ($schedule->releases()->withoutGlobalScopes()->count() >= $schedule->months) {
                $schedule->status = AccrualSchedule::STATUS_COMPLETED;
            }
            $schedule->save();
        });

        return $count;
    }
}
