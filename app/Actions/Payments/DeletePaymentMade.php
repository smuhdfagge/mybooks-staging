<?php

namespace App\Actions\Payments;

use App\Models\PaymentMade;
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

        return null;
    }
}
