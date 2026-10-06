<?php

namespace App\Actions\Assembly;

use App\Enums\AssemblyOrderStatus;
use App\Models\AssemblyOrder;
use App\Models\AssemblyOrderItem;
use App\Models\Item;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Undo build" (session 14): a completed order goes back to draft.
 *
 * Only while what it made is all still in stock and free: none of its cost
 * layers sold, used or reserved by an invoice. Then those layers go, each
 * component goes back into the cost layer it came from (so exactly at the
 * cost it left at), and the extra costs journal is reversed (dated today,
 * the original stays). For a break-down it is the other way round.
 *
 * Checked against the order's date too, since that is the movement being
 * undone (lock dates, closed periods).
 */
class UndoAssemblyOrder
{
    use MovesAssemblyStock;

    public function __construct(private JournalService $journals) {}

    public function handle(AssemblyOrder $order): AssemblyOrder
    {
        return DB::transaction(function () use ($order) {
            $order = AssemblyOrder::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($reason = $this->blockedBecause($order)) {
                throw ValidationException::withMessages(['order' => $reason]);
            }
            $this->assertDateOpen($order, $order->movementDate());

            $tenantId = (int) $order->tenant_id;
            $bom = $order->billOfMaterial()->withoutGlobalScopes()->firstOrFail();
            $finished = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($bom->item_id);
            $lines = $order->items()->get();
            $items = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
            $made = (float) $order->quantity_made;
            $to = (int) $order->to_warehouse_id;
            $number = $order->order_number;

            if ($order->isBreakdown()) {
                foreach ($lines as $line) {
                    if (! $this->stillInStock($order, (int) $line->item_id, $to, (float) $line->quantity)) {
                        throw ValidationException::withMessages(['order' => "Some of the {$items->get($line->item_id)?->name} got back from {$number} have been sold, used or reserved since, so it can't be undone."]);
                    }
                }
                foreach ($lines as $line) {
                    $this->removeMade($order, (int) $line->item_id, $to, (float) $line->quantity, (float) $line->cost, "Undo break-down {$number}: taken out again");
                }
                $this->putBack($order, $finished, AssemblyOrder::class, $order->id, "Undo break-down {$number}: back in stock");
            } else {
                if (! $this->stillInStock($order, $finished->id, $to, $made)) {
                    throw ValidationException::withMessages(['order' => "Some of the {$this->qty($made)} {$finished->name} made in {$number} have been sold, used or reserved since, so this build can't be undone."]);
                }
                $this->removeMade($order, $finished->id, $to, $made, (float) $order->total_cost, "Undo build {$number}: taken out again");
                foreach ($lines as $line) {
                    $this->putBack($order, $items->get($line->item_id), AssemblyOrderItem::class, $line->id, "Undo build {$number}: back in stock");
                }
                $this->journals->reverseDocumentJournal(AssemblyOrder::class, $order->id, "Build {$number} undone");
            }

            foreach ($lines as $line) {
                $line->forceFill(['quantity' => null, 'cost' => 0])->save();
            }
            $order->costs()->update(['amount' => null]);
            $order->forceFill([
                'status' => AssemblyOrderStatus::Draft->value,
                'quantity_made' => null,
                'components_cost' => 0,
                'extra_cost' => 0,
                'total_cost' => 0,
                'unit_cost' => 0,
                'completed_at' => null,
            ])->save();

            return $order->fresh(['items', 'costs']);
        });
    }

    public function blockedBecause(AssemblyOrder $order): ?string
    {
        if (! $order->isCompleted()) {
            return "{$order->order_number} hasn't been completed, so there is nothing to undo.";
        }
        if (! $order->items()->exists() || ! $order->to_warehouse_id) {
            return "{$order->order_number} was made before builds could be undone. Adjust the stock by hand instead.";
        }

        return null;
    }
}
