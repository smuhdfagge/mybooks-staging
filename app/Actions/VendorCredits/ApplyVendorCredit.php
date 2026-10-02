<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\Bill;
use App\Models\VendorCredit;
use App\Models\VendorCreditApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Using (part of) an open supplier credit against one of the same
 * supplier's bills. The bill's balance goes down and the credit's balance
 * goes down; nothing is posted, as both sit in accounts payable.
 */
class ApplyVendorCredit
{
    public function handle(VendorCredit $credit, Bill $bill, float $amount, ?string $date = null): VendorCreditApplication
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($credit, $bill, $amount, $date) {
            $credit = VendorCredit::lockForUpdate()->findOrFail($credit->id);
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            if ($credit->status !== VendorCreditStatus::Open->value) {
                throw ValidationException::withMessages(['amount' => 'Only an open supplier credit can be used.']);
            }
            if ((int) $bill->vendor_id !== (int) $credit->vendor_id) {
                throw ValidationException::withMessages(['bill_id' => 'That bill is from a different supplier.']);
            }
            if (in_array($bill->status, ['draft', 'cancelled', 'paid'], true)) {
                throw ValidationException::withMessages(['bill_id' => "Bill {$bill->bill_number} is {$bill->status}."]);
            }
            if ($amount <= 0 || $amount - (float) $credit->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'Only '.number_format((float) $credit->balance, 2).' of this credit is left.']);
            }
            if ($amount - (float) $bill->balance_due > 0.005) {
                throw ValidationException::withMessages(['amount' => "Bill {$bill->bill_number} only has ".number_format((float) $bill->balance_due, 2).' left to pay.']);
            }

            $application = VendorCreditApplication::create([
                'tenant_id' => $credit->tenant_id,
                'vendor_credit_id' => $credit->id,
                'bill_id' => $bill->id,
                'amount' => $amount,
                'applied_date' => $date ?? now()->toDateString(),
                'applied_by' => auth()->id(),
            ]);

            ReduceVendorCreditBalance::by($credit, $amount);
            $bill->updateBalances();

            return $application;
        });
    }
}
