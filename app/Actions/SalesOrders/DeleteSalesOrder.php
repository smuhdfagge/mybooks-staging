<?php

namespace App\Actions\SalesOrders;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a sales order, the same from the web, the API and the bulk
 * action (finding R3). The web delete had no checks at all, so an order
 * could vanish from under its invoices and delivery notes.
 */
class DeleteSalesOrder
{
    public function handle(SalesOrder $order): void
    {
        if ($reason = $this->blockedBecause($order)) {
            throw ValidationException::withMessages(['sales_order' => $reason]);
        }

        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->delete();
        });
    }

    public function blockedBecause(SalesOrder $order): ?string
    {
        if ($order->invoices()->exists()) {
            return "Sales order {$order->order_number} has been invoiced. Delete or cancel the invoices first.";
        }
        if ($order->deliveryNotes()->exists()) {
            return "Sales order {$order->order_number} has delivery notes. Delete them first.";
        }

        return null;
    }
}
