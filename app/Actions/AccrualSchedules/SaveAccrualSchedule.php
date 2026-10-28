<?php

namespace App\Actions\AccrualSchedules;

use App\Models\AccrualSchedule;
use App\Models\ChartOfAccount;
use App\Services\AccountCodeService;
use App\Services\Accounting\FinancialStatements;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or change a prepaid expense / deferred revenue schedule (S9).
 *
 * Linked to a bill, expense or invoice: a setup journal moves the amount
 * from the account that document used into the balance account (see
 * ScheduleSetup). Not linked: the money must already be in the balance
 * account (a journal, or a bill or expense line posted to it), enough for
 * every active schedule on it; otherwise each release took out money that
 * was never put in (audit MB-53). Months whose end has already passed (a
 * start month in the past) are released straight away, so the books don't
 * wait for the daily run.
 *
 * A schedule can only be changed while nothing has been released; after
 * that, cancel it and set up a new one.
 *
 * $data keys: type, description, total_amount, start_month (YYYY-MM or a
 * date), months, balance_account_id (empty = the default account),
 * pl_account_id, source_type, source_id, reference, notes.
 */
class SaveAccrualSchedule
{
    public function __construct(
        protected ReleaseAccrualSchedule $release,
        protected ScheduleSetup $setup,
        protected FinancialStatements $statements,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, array $data, ?AccrualSchedule $schedule = null, ?int $userId = null): AccrualSchedule
    {
        $prepaid = $data['type'] === AccrualSchedule::TYPE_PREPAID;

        $saved = DB::transaction(function () use ($tenantId, $data, $schedule, $userId, $prepaid) {
            if ($schedule) {
                $schedule = AccrualSchedule::lockForUpdate()->findOrFail($schedule->id);
                if (! $schedule->isActive() || $schedule->hasReleases()) {
                    throw ValidationException::withMessages(['schedule' => 'Months have already been released from this schedule, so it can\'t be changed. Cancel it and set up a new one.']);
                }
            }

            $balance = ! empty($data['balance_account_id'])
                ? ChartOfAccount::where('tenant_id', $tenantId)->find($data['balance_account_id'])
                : $this->defaultBalanceAccount($tenantId, $prepaid);
            $pl = ChartOfAccount::where('tenant_id', $tenantId)->find($data['pl_account_id'] ?? null);

            $errors = [];
            if (! $balance || $balance->type !== ($prepaid ? 'asset' : 'liability') || in_array($balance->sub_type, ['cash', 'bank'], true)) {
                $errors['balance_account_id'] = $prepaid
                    ? 'Choose an asset account that holds the amount paid in advance, such as Prepaid Expenses.'
                    : 'Choose a liability account that holds the amount received in advance, such as Deferred Revenue.';
            }
            if (! $pl || $pl->type !== ($prepaid ? 'expense' : 'income')) {
                $errors['pl_account_id'] = $prepaid ? 'Choose the expense account each month goes to.' : 'Choose the income account each month goes to.';
            }
            [$sourceType, $sourceId] = $this->source($tenantId, $data, $prepaid, $errors);
            // Tiny totals over many months could leave a month at nothing (or
            // the last one below nothing after rounding).
            if (min(AccrualSchedule::split($data['total_amount'], (int) $data['months'])) <= 0) {
                $errors['total_amount'] = "The total is too small to spread over {$data['months']} months.";
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $values = [
                'type' => $data['type'],
                'description' => $data['description'],
                'total_amount' => Money::round($data['total_amount']),
                'start_date' => Carbon::parse(strlen((string) $data['start_month']) === 7 ? $data['start_month'].'-01' : $data['start_month'])->startOfMonth()->toDateString(),
                'months' => (int) $data['months'],
                'balance_account_id' => $balance->id,
                'pl_account_id' => $pl->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($schedule) {
                // Undo the old setup; the new one (if still linked) follows.
                $this->setup->undo($schedule, (float) $schedule->total_amount, 'Schedule changed');
                $schedule->update($values + ['setup_journal_id' => null]);
            } else {
                $schedule = AccrualSchedule::create($values + [
                    'tenant_id' => $tenantId,
                    'schedule_number' => AccrualSchedule::generateNumber($tenantId),
                    'status' => AccrualSchedule::STATUS_ACTIVE,
                    'released_amount' => 0,
                    'created_by' => $userId,
                ]);
            }

            if ($sourceType) {
                $this->setup->post($schedule);
            } else {
                $this->assertCovered($schedule, $balance);
            }

            return $schedule;
        });

        // Catch up on months already past. After the save, so a problem
        // posting (reported) doesn't lose the schedule; the daily run retries.
        try {
            $this->release->handle($saved);
        } catch (\Throwable $e) {
            report($e);
        }

        return $saved->fresh();
    }

    /**
     * An unlinked schedule takes its months out of the balance account, so
     * the account must already hold what every active schedule on it still
     * has to release, this one included.
     */
    protected function assertCovered(AccrualSchedule $schedule, ChartOfAccount $balance): void
    {
        $held = (float) ($this->statements->accountBalances($schedule->tenant_id, null, '2999-12-31')->firstWhere('id', $balance->id)->balance ?? 0);
        $needed = AccrualSchedule::withoutGlobalScope('tenant')
            ->where('tenant_id', $schedule->tenant_id)
            ->where('balance_account_id', $balance->id)
            ->where('status', AccrualSchedule::STATUS_ACTIVE)
            ->get()
            ->sum(fn (AccrualSchedule $s) => $s->remaining());

        if ($held + 0.005 < $needed) {
            throw ValidationException::withMessages(['balance_account_id' => sprintf(
                '%s holds %s, but its active schedules still need %s, this one included. Record the payment into %s first (%s), or link the %s it was %s on.',
                $balance->name, Money::format($held), Money::format($needed), $balance->name,
                $schedule->isPrepaid() ? 'a journal, or a bill or expense line posted to it' : 'for example with a journal',
                $schedule->isPrepaid() ? 'bill or expense' : 'invoice', $schedule->isPrepaid() ? 'paid' : 'billed',
            )]);
        }
    }

    /** Prepaid Expenses or Deferred Revenue, added to the chart if the business doesn't have it. */
    protected function defaultBalanceAccount(int $tenantId, bool $prepaid): ChartOfAccount
    {
        $code = AccountCodeService::resolve($tenantId, $prepaid ? 'prepaid_expenses' : 'deferred_revenue');

        // A deleted one is brought back (the code is unique per business).
        $account = ChartOfAccount::withTrashed()->firstOrNew(['tenant_id' => $tenantId, 'account_code' => $code]);
        if (! $account->exists) {
            $account->fill($prepaid
                ? ['name' => 'Prepaid Expenses', 'type' => 'asset', 'sub_type' => 'other_current_asset']
                : ['name' => 'Deferred Revenue', 'type' => 'liability', 'sub_type' => 'other_current_liability']);
            $account->fill(['is_active' => true, 'is_system' => true])->save();
        } elseif ($account->trashed()) {
            $account->restore();
        }

        return $account;
    }

    /**
     * The linked bill or expense (prepaid) or invoice (deferred), checked
     * to belong to this business.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $errors
     * @return array{0: ?string, 1: ?int}
     */
    protected function source(int $tenantId, array $data, bool $prepaid, array &$errors): array
    {
        $type = $data['source_type'] ?? null;
        $id = $data['source_id'] ?? null;
        if (! $type || ! $id) {
            return [null, null];
        }
        $allowed = $prepaid ? ['bill', 'expense'] : ['invoice'];
        $model = AccrualSchedule::SOURCES[$type][0] ?? null;
        if (! in_array($type, $allowed, true) || ! $model || ! $model::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereKey($id)->exists()) {
            $errors['source'] = $prepaid ? 'Link a bill or expense of this business, or leave it empty.' : 'Link an invoice of this business, or leave it empty.';

            return [null, null];
        }

        return [$type, (int) $id];
    }
}
