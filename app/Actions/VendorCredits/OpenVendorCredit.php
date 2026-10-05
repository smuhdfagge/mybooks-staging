<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\Inventory;
use App\Models\VendorCredit;
use App\Models\VendorCreditItem;
use App\Models\Warehouse;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening (posting) a supplier credit, in one transaction:
 * - returned goods leave stock at their cost: from the linked bill's own
 *   cost layers first, otherwise the item's FIFO or weighted average cost;
 * - the journal is posted (JournalService::createVendorCreditJournal);
 * - the whole credit becomes available to use against bills or refund.
 *
 * Goods leave the credit's warehouse if one was chosen, else the linked
 * bill's, else the default one (session 12); the one used is kept on the
 * credit so voiding puts the goods back there.
 */
class OpenVendorCredit
{
    public function __construct(
        protected StockValuationService $stock,
        protected JournalService $journals,
    ) {}

    public function handle(VendorCredit $credit): VendorCredit
    {
        if ($credit->status !== VendorCreditStatus::Draft->value) {
            throw ValidationException::withMessages(['status' => 'Only a draft supplier credit can be opened.']);
        }

        return DB::transaction(function () use ($credit) {
            $credit = VendorCredit::lockForUpdate()->findOrFail($credit->id);
            $lines = $credit->items()->with('item')->get();
            $stocked = $lines->filter(fn (VendorCreditItem $l) => $l->isStocked());

            $warehouseId = $credit->warehouse_id
                ? (int) $credit->warehouse_id
                : ($credit->bill?->warehouse_id ? (int) $credit->bill->warehouse_id : Warehouse::defaultIdFor($credit->tenant_id));
            $credit->forceFill(['warehouse_id' => $warehouseId]);

            $this->checkQuantities($credit, $stocked, $warehouseId);

            foreach ($stocked as $line) {
                $cost = $this->stock->returnToSupplier($line->item, (float) $line->quantity, VendorCredit::class, $credit->id, $credit->bill_id, $warehouseId);
                $line->forceFill(['unit_cost' => round($cost / (float) $line->quantity, 4)])->save();
            }

            $credit->forceFill([
                'status' => VendorCreditStatus::Open->value,
                'balance' => $credit->total,
                'stock_returned_at' => $stocked->isNotEmpty() ? now() : null,
            ])->save();

            $this->journals->createVendorCreditJournal($credit);

            return $credit;
        });
    }

    /**
     * Goods can only go back if we have them, and not more than the linked
     * bill brought in (less what earlier credits on that bill sent back).
     *
     * @param  Collection<int, VendorCreditItem>  $stocked
     */
    protected function checkQuantities(VendorCredit $credit, $stocked, int $warehouseId): void
    {
        foreach ($stocked->groupBy('item_id') as $itemId => $group) {
            $qty = (float) $group->sum('quantity');
            $name = $group->first()->item->name;

            if ($credit->bill_id) {
                $billed = (float) $credit->bill->items()->where('item_id', $itemId)->sum('quantity');
                $returnedBefore = (float) VendorCreditItem::where('item_id', $itemId)
                    ->whereHas('vendorCredit', fn ($q) => $q->where('bill_id', $credit->bill_id)
                        ->whereKeyNot($credit->id)
                        ->whereIn('status', [VendorCreditStatus::Open->value, VendorCreditStatus::Closed->value]))
                    ->sum('quantity');
                if ($qty - ($billed - $returnedBefore) > 0.0001) {
                    $left = max(0, $billed - $returnedBefore);
                    throw ValidationException::withMessages(['items' => "Bill {$credit->bill->bill_number} has only {$this->qty($left)} of {$name} left to return."]);
                }
            }

            $onHand = (float) Inventory::where('tenant_id', $credit->tenant_id)->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->value('quantity');
            if ($qty - $onHand > 0.0001) {
                throw ValidationException::withMessages(['items' => "Only {$this->qty($onHand)} of {$name} is in stock, so {$this->qty($qty)} can't be sent back."]);
            }
        }
    }

    private function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, '.', ','), '0'), '.');
    }
}
