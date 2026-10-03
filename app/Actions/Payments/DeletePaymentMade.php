<?php

namespace App\Actions\Payments;

use App\Models\PaymentMade;
use App\Models\StatutoryRemittance;
use App\Models\VendorAdvanceApplication;
use App\Services\BankService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a vendor payment, the same from the web and the API (finding
 * R3): the money goes back into the bank's balance, which the API skipped.
 * The Deleting/Deleted events reverse the journal and update the bill.
 * An advance that has been used can't be deleted; deleting the payment
 * that used it gives the amount back to the advance.
 */
class DeletePaymentMade
{
    public function __construct(protected BankService $bank) {}

    public function handle(PaymentMade $payment): void
    {
        if ($reason = $this->blockedBecause($payment)) {
            throw ValidationException::withMessages(['payment' => $reason]);
        }

        DB::transaction(function () use ($payment) {
            if ($payment->payment_method === PaymentMade::METHOD_ADVANCE) {
                // Undoing the use of an advance: the advance gets the amount back.
                $application = VendorAdvanceApplication::where('applied_payment_id', $payment->id)->first();
                if ($application) {
                    $advance = PaymentMade::lockForUpdate()->find($application->advance_payment_id);
                    if ($advance) {
                        $advance->unused_amount = round((float) $advance->unused_amount + (float) $application->amount, 2);
                        $advance->withoutPeriodValidation()->save();
                    }
                    $application->delete();
                }
            } else {
                $this->bank->credit($payment->bank_id, (float) $payment->amount, "Payment made #{$payment->payment_number} deleted");
            }
            $payment->delete();
        });
    }

    public function blockedBecause(PaymentMade $payment): ?string
    {
        if ($payment->is_advance && $payment->advanceApplications()->exists()) {
            return 'This advance has been used against bills. Delete those payments first.';
        }

        // WHT already paid over to the tax authority for this payment's month
        // can't be taken back by deleting the payment, or WHT payable would go
        // negative. Delete that WHT payment record first.
        if ((float) $payment->wht_amount > 0 && $this->whtRemitted($payment)) {
            return 'The WHT on this payment has already been paid over to the tax authority. Delete that WHT payment record first.';
        }

        return null;
    }

    private function whtRemitted(PaymentMade $payment): bool
    {
        $date = $payment->payment_date;

        return StatutoryRemittance::where('body', StatutoryRemittance::BODY_WHT)
            ->where('period_start', '<=', $date)
            ->where('period_end', '>=', $date->copy()->startOfDay())
            ->when($payment->wht_authority, fn ($q) => $q->where('wht_authority', $payment->wht_authority))
            ->when($payment->wht_state, fn ($q) => $q->where('wht_state', $payment->wht_state))
            ->exists();
    }
}
