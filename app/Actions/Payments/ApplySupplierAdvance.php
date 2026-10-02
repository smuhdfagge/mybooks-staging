<?php

namespace App\Actions\Payments;

use App\Models\Bill;
use App\Models\PaymentMade;
use App\Models\VendorAdvanceApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Using (part of) a supplier advance against a bill, like applying a
 * customer deposit to an invoice: the bill is paid by a payment with
 * method "advance" (Dr accounts payable, Cr supplier advances); no money
 * moves through the bank.
 */
class ApplySupplierAdvance
{
    public function handle(PaymentMade $advance, Bill $bill, float $amount, ?string $date = null, ?string $notes = null): VendorAdvanceApplication
    {
        return DB::transaction(function () use ($advance, $bill, $amount, $date, $notes) {
            $advance = PaymentMade::lockForUpdate()->findOrFail($advance->id);
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            try {
                return $advance->applyToBill($bill, $amount, $date, $notes);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['amount' => $e->getMessage()]);
            }
        });
    }
}
