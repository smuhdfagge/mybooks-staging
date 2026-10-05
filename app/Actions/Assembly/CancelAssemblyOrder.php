<?php

namespace App\Actions\Assembly;

use App\Enums\AssemblyOrderStatus;
use App\Models\AssemblyOrder;
use Illuminate\Validation\ValidationException;

/**
 * Cancelling a draft assembly order (session 14). It moved nothing, so
 * nothing changes; the order is kept, marked cancelled. A completed order
 * is undone instead.
 */
class CancelAssemblyOrder
{
    public function handle(AssemblyOrder $order): AssemblyOrder
    {
        if ($reason = $this->blockedBecause($order)) {
            throw ValidationException::withMessages(['order' => $reason]);
        }
        $order->forceFill(['status' => AssemblyOrderStatus::Cancelled->value, 'cancelled_at' => now()])->save();

        return $order;
    }

    public function blockedBecause(AssemblyOrder $order): ?string
    {
        return match ($order->status) {
            AssemblyOrder::STATUS_DRAFT => null,
            AssemblyOrder::STATUS_COMPLETED => "{$order->order_number} has been completed. Use \"Undo build\" to reverse it.",
            default => "{$order->order_number} is already cancelled.",
        };
    }
}
