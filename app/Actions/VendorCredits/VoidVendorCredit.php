<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\Inventory;
use App\Models\VendorCredit;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voiding a supplier credit that hasn't been used or refunded: its journal
 * is kept and reversed, and returned goods go back into the stock layers
 * they came from. A draft is simply marked void.
 */
class VoidVendorCredit
{
    public function __construct(
        protected StockValuationService $stock,
        protected JournalService $journals,
    ) {}

    public function handle(VendorCredit $credit): VendorCredit
    {
        if ($reason = $this->blockedBecause($credit)) {
            throw ValidationException::withMessages(['vendor_credit' => $reason]);
        }

        return DB::transaction(function () use ($credit) {
            $credit = VendorCredit::lockForUpdate()->findOrFail($credit->id);

            if ($credit->status === VendorCreditStatus::Open->value) {
                $this->journals->reverseDocumentJournal(VendorCredit::class, $credit->id, 'Supplier credit voided');

                if ($credit->stock_returned_at) {
                    // Put the average cost back as it was, then the layers and quantity.
                    foreach ($credit->items()->with('item')->get()->filter->isStocked() as $line) {
                        $inventory = Inventory::where('tenant_id', $credit->tenant_id)->where('item_id', $line->item_id)->lockForUpdate()->first();
                        if ($inventory) {
                            $qty = (float) $inventory->quantity;
                            $back = (float) $line->quantity;
                            if ($qty + $back > 0.00001) {
                                $inventory->unit_cost = round(($qty * (float) $inventory->unit_cost + $back * (float) $line->unit_cost) / ($qty + $back), 4);
                                $inventory->save();
                            }
                        }
                    }
                    $this->stock->returnStock(VendorCredit::class, $credit->id);
                }
            }

            $credit->forceFill(['status' => VendorCreditStatus::Void->value, 'balance' => 0, 'stock_returned_at' => null])->save();

            return $credit;
        });
    }

    public function blockedBecause(VendorCredit $credit): ?string
    {
        if ($credit->status === VendorCreditStatus::Void->value) {
            return "Supplier credit {$credit->vendor_credit_number} is already void.";
        }
        if ($credit->applications()->exists() || $credit->refunds()->exists()) {
            return "Supplier credit {$credit->vendor_credit_number} has been used against bills or refunded, so it can't be voided.";
        }

        return null;
    }
}
