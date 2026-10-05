<?php

namespace App\Actions\VatReturns;

use App\Models\LockDateChange;
use App\Models\User;
use App\Models\VatReturnFiling;
use App\Services\Accounting\LockDates;
use App\Services\Accounting\VatReturn;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reopening a filed VAT return (session 11), e.g. to file an amended return
 * after a late document. With a reason, kept in the lock date history:
 * - the settlement journal is reversed, dated the month's last day like the
 *   settlement itself, so the month can be settled again when re-filed;
 * - the filed figures are removed, so the month can be filed again.
 * Refused when the month's end is in a closed period or behind a lock date
 * the user can't pass (never behind the lock for everyone), and while a
 * later month is filed (its line 100 came from this one).
 */
class ReopenVatReturn
{
    public function __construct(protected JournalService $journals) {}

    public function handle(int $tenantId, string $month, string $reason, ?User $user = null): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Say why the return is being reopened (at least 5 characters). It is kept in the history.']);
        }

        DB::transaction(function () use ($tenantId, $month, $reason, $user) {
            $filing = VatReturnFiling::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('month', $month)
                ->lockForUpdate()->first();
            if (! $filing) {
                throw ValidationException::withMessages(['month' => 'The VAT return for this month hasn\'t been filed.']);
            }
            $label = Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y');

            $later = VatReturnFiling::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('month', '>', $month)
                ->orderBy('month')->value('month');
            if ($later) {
                $laterLabel = Carbon::createFromFormat('Y-m-d', $later.'-01')->format('F Y');
                throw ValidationException::withMessages(['month' => "The return for {$laterLabel} is filed and carried this month's figures forward. Reopen it first."]);
            }

            $end = Carbon::createFromFormat('Y-m-d', $month.'-01')->endOfMonth()->startOfDay();
            if ($blocked = LockDates::instance()->blockReason($end, $tenantId, $user)) {
                throw ValidationException::withMessages(['month' => $blocked]);
            }

            $journal = $filing->settlementJournal()->withoutGlobalScopes()->first();
            if ($journal && $journal->status === 'posted') {
                $reversal = $this->journals->reverseJournal($journal, "VAT return for {$label} reopened", $end->toDateString());
                // Typed like the settlement, so the month's VAT figures leave both out.
                $reversal->forceFill(['journal_type' => VatReturn::SETTLEMENT])->save();
            }

            $paid = (float) $filing->vat_payable > 0
                ? 'VAT payable ₦'.number_format((float) $filing->vat_payable, 2)
                : 'credit carried forward ₦'.number_format((float) $filing->credit_carried_forward, 2);
            LockDateChange::record($tenantId, LockDates::VAT_RETURN, $end, null, $reason,
                "VAT return for {$label} reopened (filed {$filing->filed_at->format('j M Y')}"
                .($filing->reference ? ", NRS reference {$filing->reference}" : '').", {$paid})", $user?->id);

            $filing->delete();
        });
    }
}
