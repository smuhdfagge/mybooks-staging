<?php

namespace App\Actions\Bills;

use App\Models\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a bill, the same from the web, the API and the bulk action
 * (finding R3). Refused while payments exist. Goods the bill put into
 * stock are taken out again, unless some have been sold or used already.
 * The journal is kept and reversed by the BillDeleting event (M6).
 */
class DeleteBill
{
    public function handle(Bill $bill): void
    {
        if ($reason = $this->blockedBecause($bill)) {
            throw ValidationException::withMessages(['bill' => $reason]);
        }

        DB::transaction(function () use ($bill) {
            if ($bill->inventory_updated_at) {
                $bill->reverseInventory();
            }
            $bill->items()->delete();
            $bill->delete();
        });
    }

    public function blockedBecause(Bill $bill): ?string
    {
        if ((float) $bill->amount_paid > 0 || $bill->payments()->exists()) {
            return "Bill {$bill->bill_number} has payments. Remove them first.";
        }
        if ($bill->inventory_updated_at && ! $bill->stockStillOnHand()) {
            return "Some goods from bill {$bill->bill_number} have already been sold or used, so it can't be deleted.";
        }

        return null;
    }
}
