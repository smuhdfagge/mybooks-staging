<?php

namespace App\Actions\Accruals;

use App\Models\AccrualSchedule;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Services\BankService;
use App\Services\JournalService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Setting up a prepaid expense or income-received-in-advance schedule.
 *
 * How the amount gets into the prepaid / deferred account (funding):
 *   bank        prepaid:  Dr Prepaid, Cr bank/cash     deferred: Dr bank/cash, Cr Deferred revenue
 *   reclassify  prepaid:  Dr Prepaid, Cr the expense   deferred: Dr the income, Cr Deferred revenue
 *   existing    nothing is posted now (it is already in the account)
 * The months are then released by ReleaseAccrualSchedule.
 *
 * $data keys: type, description, total_amount, recorded_date, start_date,
 * months, balance_account_id, pl_account_id, funding, funding_account_id, notes.
 */
class CreateAccrualSchedule
{
    public function __construct(
        protected JournalService $journals,
        protected BankService $banks,
        protected ReleaseAccrualSchedule $release,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, array $data, ?int $userId = null): AccrualSchedule
    {
        $prepaid = $data['type'] === AccrualSchedule::TYPE_PREPAID;
        $balance = ChartOfAccount::where('tenant_id', $tenantId)->find($data['balance_account_id']);
        $pl = ChartOfAccount::where('tenant_id', $tenantId)->find($data['pl_account_id']);
        $funding = $data['funding'] ?? 'bank';
        $fundingAccount = ! empty($data['funding_account_id']) ? ChartOfAccount::where('tenant_id', $tenantId)->find($data['funding_account_id']) : null;

        $errors = [];
        if (! $balance || $balance->type !== ($prepaid ? 'asset' : 'liability')) {
            $errors['balance_account_id'] = $prepaid ? 'Choose an asset account, such as Prepaid Expenses.' : 'Choose a liability account, such as Deferred Revenue.';
        }
        if (! $pl || $pl->type !== ($prepaid ? 'expense' : 'income')) {
            $errors['pl_account_id'] = $prepaid ? 'Choose the expense account each month goes to.' : 'Choose the income account each month goes to.';
        }
        if ($funding === 'bank' && (! $fundingAccount || ! in_array($fundingAccount->sub_type, ['cash', 'bank'], true))) {
            $errors['funding_account_id'] = 'Choose the bank or cash account the money went out of or came into.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $amount = Money::round($data['total_amount']);

        return DB::transaction(function () use ($tenantId, $data, $userId, $prepaid, $balance, $pl, $funding, $fundingAccount, $amount) {
            $schedule = AccrualSchedule::create([
                'tenant_id' => $tenantId,
                'schedule_number' => AccrualSchedule::generateNumber($tenantId),
                'type' => $data['type'],
                'description' => $data['description'],
                'total_amount' => $amount,
                'recorded_date' => $data['recorded_date'] ?? $data['start_date'],
                'start_date' => $data['start_date'],
                'months' => (int) $data['months'],
                'balance_account_id' => $balance->id,
                'pl_account_id' => $pl->id,
                'funding' => $funding,
                'funding_account_id' => $funding === 'bank' ? $fundingAccount?->id : null,
                'status' => AccrualSchedule::STATUS_ACTIVE,
                'released_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            if ($funding !== 'existing') {
                $other = $funding === 'bank' ? $fundingAccount : $pl;
                $lines = $prepaid
                    ? [[$balance->account_code, $amount, 0.0, $schedule->description], [$other->account_code, 0.0, $amount, $schedule->description]]
                    : [[$other->account_code, $amount, 0.0, $schedule->description], [$balance->account_code, 0.0, $amount, $schedule->description]];
                $what = $prepaid ? 'Paid in advance' : 'Received in advance';
                $this->journals->postLines($schedule, $schedule->schedule_number, $schedule->recorded_date,
                    "{$what}: {$schedule->description} ({$schedule->schedule_number})", $lines, $userId);

                // Keep the bank's own running balance in step when the ledger account is a bank's.
                if ($funding === 'bank' && ($bank = Bank::where('chart_of_account_id', $fundingAccount->id)->first())) {
                    $prepaid
                        ? $this->banks->debit($bank->id, $amount, "Prepayment {$schedule->schedule_number}")
                        : $this->banks->credit($bank->id, $amount, "Received in advance {$schedule->schedule_number}");
                }
            }

            // Months already due (a schedule that started in the past) are released now.
            $this->release->handle($schedule);

            return $schedule->fresh();
        });
    }
}
