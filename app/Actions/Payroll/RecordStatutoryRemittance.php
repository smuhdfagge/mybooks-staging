<?php

namespace App\Actions\Payroll;

use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\StatutoryRemittance;
use App\Services\BankService;
use App\Services\JournalService;
use App\Services\Payroll\StatutoryScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording a payment of one statutory schedule group for a pay month,
 * e.g. "PAYE to Kano IRS for September 2026, from bank X on date Y,
 * reference Z". Posts Dr liability, Cr bank through JournalService and
 * takes the money off the bank's balance. Part payments are allowed; more
 * than is still owed for the group, or more than the liability account
 * holds, is refused.
 *
 * $data keys: group_key, amount, paid_on, bank_id, payment_method, reference.
 */
class RecordStatutoryRemittance
{
    public function __construct(
        protected StatutoryScheduleService $schedules,
        protected JournalService $journals,
        protected BankService $banks,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(int $tenantId, string $schedule, Carbon $month, array $data, ?int $userId = null): StatutoryRemittance
    {
        $month = $month->copy()->startOfMonth();
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($tenantId, $schedule, $month, $data, $userId, $amount) {
            $built = $this->schedules->build($tenantId, $schedule, $month);
            $group = $built['groups'][$data['group_key']] ?? null;
            if (! $group) {
                throw ValidationException::withMessages(['group_key' => 'Nothing is owed to that payee for this month.']);
            }
            if ($group['key'] === 'none') {
                $what = $schedule === 'paye' ? 'PAYE state' : 'Pension Fund Administrator';
                throw ValidationException::withMessages(['group_key' => "Set the {$what} on these employees first, so the payment goes to the right place."]);
            }

            // Lock what was paid already, so two quick submits can't both pass.
            $paid = (float) StatutoryRemittance::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('schedule', $schedule)
                ->whereDate('period', $month->toDateString())->where('group_key', $group['key'])
                ->lockForUpdate()->sum('amount');
            $outstanding = round($group['amount'] - $paid, 2);
            if ($amount - $outstanding > 0.005) {
                throw ValidationException::withMessages(['amount' => 'That is more than is still owed to '.$group['label'].' for '.$month->format('F Y').' ('.number_format(max(0, $outstanding), 2).').']);
            }

            $code = $built['ledger']['account_code'];
            $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', $code)->lockForUpdate()->first();
            if (! $account || $amount - (float) $account->current_balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'That is more than the ledger shows as owed in '.$code.' '.($account->name ?? '').' ('.number_format((float) ($account->current_balance ?? 0), 2).').']);
            }

            $bank = ! empty($data['bank_id']) ? Bank::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($data['bank_id']) : null;
            $remittance = StatutoryRemittance::create([
                'tenant_id' => $tenantId,
                'schedule' => $schedule,
                'period' => $month->toDateString(),
                'group_key' => $group['key'],
                'group_label' => $group['label'],
                'amount' => $amount,
                'paid_on' => $data['paid_on'],
                'bank_id' => $bank?->id,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'created_by' => $userId,
            ]);

            $journal = $this->journals->createPayrollRemittanceJournal(
                $tenantId, $code, $amount, (string) $data['paid_on'], $remittance->payment_method, $remittance->reference,
                $bank, self::describe($schedule, $group['label'], $month), $remittance
            );
            $remittance->update(['journal_id' => $journal->id]);

            $this->banks->debit($bank?->id, $amount, 'Statutory remittance #'.$remittance->id);

            return $remittance;
        });
    }

    public static function describe(string $schedule, string $payee, Carbon $month): string
    {
        $period = $month->format('F Y');

        return match ($schedule) {
            'paye' => "PAYE to {$payee} IRS for {$period}",
            'pension' => "Pension to {$payee} for {$period}",
            'nhf' => "NHF to FMBN for {$period}",
            'nsitf' => "NSITF for {$period}",
            'itf' => "ITF for {$period}",
            default => "Remittance for {$period}",
        };
    }
}
