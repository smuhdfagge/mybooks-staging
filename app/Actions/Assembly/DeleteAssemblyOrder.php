<?php

namespace App\Actions\Assembly;

use App\Models\AssemblyOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting an assembly order (session 14): only a draft or a cancelled
 * one, which moved nothing. Checked against lock dates like other
 * documents.
 */
class DeleteAssemblyOrder
{
    use MovesAssemblyStock;

    public function handle(AssemblyOrder $order): void
    {
        if ($reason = $this->blockedBecause($order)) {
            throw ValidationException::withMessages(['order' => $reason]);
        }
        $this->assertDateOpen($order, $order->movementDate());

        DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->costs()->delete();
            $order->delete();
        });
    }

    public function blockedBecause(AssemblyOrder $order): ?string
    {
        return $order->isCompleted()
            ? "{$order->order_number} has been completed, so it can't be deleted. Use \"Undo build\" first."
            : null;
    }
}
