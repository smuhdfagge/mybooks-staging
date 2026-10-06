<?php

namespace App\Actions\Assembly;

use App\Enums\AssemblyOrderStatus;
use App\Models\AssemblyOrder;
use App\Models\AssemblyOrderCost;
use App\Models\AssemblyOrderItem;
use App\Models\BillOfMaterial;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Completing an assembly order (session 14), on the order's date.
 *
 * A build:
 * - Only free stock (on hand less reserved for invoices) of each component
 *   can be used, in the components' warehouse. The old code didn't check.
 * - The components leave through StockValuationService::issue(), so their
 *   cost is their FIFO layers or weighted average (the old code used the
 *   item's cost price and left the cost layers as they were).
 * - The finished goods go into their warehouse as one cost layer (two when
 *   needed for the kobo, see putIn()) worth the components' cost plus the
 *   extra costs, exactly.
 * - Components to finished goods is Inventory to Inventory: no journal.
 *   Extra costs post Dr Inventory, Cr the account on each cost line
 *   (Production Costs Applied unless the bill says otherwise).
 * - Wastage is part of what the components cost, so it is in the finished
 *   goods' cost (no separate loss line).
 *
 * A break-down takes finished items out at their cost and puts the
 * components back in, splitting that cost by each component's share of the
 * bill's cost at today's prices. It posts nothing.
 *
 * $actual: what really happened, each defaulting to the plan:
 *   quantity_made => float, items => [line id => quantity], costs => [cost line id => amount].
 */
class CompleteAssemblyOrder
{
    use MovesAssemblyStock;

    public function __construct(private JournalService $journals) {}

    /** @param array<string, mixed> $actual */
    public function handle(AssemblyOrder $order, array $actual = []): AssemblyOrder
    {
        return DB::transaction(function () use ($order, $actual) {
            $order = AssemblyOrder::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->isDraft()) {
                throw ValidationException::withMessages(['order' => "{$order->order_number} is already {$order->status}."]);
            }
            $this->assertDateOpen($order, $order->movementDate());

            $bom = $order->billOfMaterial()->withoutGlobalScopes()->first();
            if (! $bom) {
                throw ValidationException::withMessages(['order' => 'The bill of materials for this order has been deleted.']);
            }
            $tenantId = (int) $order->tenant_id;
            $finished = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($bom->item_id)->first();
            if (! $finished || ! $finished->track_inventory) {
                throw ValidationException::withMessages(['order' => ($finished->name ?? 'The finished item')." doesn't keep stock, so it can't be made."]);
            }
            $from = self::ownWarehouse($tenantId, $order->warehouse_id ?: Warehouse::defaultIdFor($tenantId), 'warehouse_id');
            $to = self::ownWarehouse($tenantId, $order->to_warehouse_id ?: $from->id, 'to_warehouse_id');

            // Old drafts were saved without lines.
            if (! $order->items()->exists()) {
                $order->forceFill(['planned_quantity' => $order->plannedQuantity(), 'assembly_date' => $order->movementDate()])->save();
                app(SaveAssemblyOrder::class)->planLines($order, $bom);
            }
            $lines = $order->items()->get();
            $costs = $order->costs()->get();
            $items = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');

            $made = $this->number($actual['quantity_made'] ?? null, $order->plannedQuantity(), 'quantity_made', 'the quantity');
            if ($made <= 0) {
                throw ValidationException::withMessages(['quantity_made' => $order->isBreakdown() ? 'Enter how many were broken down.' : 'Enter how many were made.']);
            }
            foreach ($lines as $index => $line) {
                $item = $items->get($line->item_id);
                if (! $item || ! $item->track_inventory) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => ($item->name ?? 'A component')." doesn't keep stock any more."]);
                }
                $line->forceFill(['quantity' => $this->number($actual['items'][$line->id] ?? null, (float) $line->planned_quantity, "items.{$index}.quantity", $item->name)]);
            }
            if ($lines->sum(fn ($l) => (float) $l->quantity) <= 0) {
                throw ValidationException::withMessages(['items' => $order->isBreakdown() ? 'Enter what you got back.' : 'Enter how much of the components was used.']);
            }
            foreach ($costs as $index => $cost) {
                $cost->forceFill(['amount' => round($this->number($actual['costs'][$cost->id] ?? null, (float) $cost->planned_amount, "costs.{$index}.amount", $cost->description), 2)]);
            }

            $order->forceFill(['assembly_date' => $order->movementDate()]);
            $order->isBreakdown()
                ? $this->breakDown($order, $finished, $lines, $items, $made, $from, $to)
                : $this->build($order, $finished, $lines, $costs, $items, $made, $from, $to);

            $order->forceFill([
                'status' => AssemblyOrderStatus::Completed->value,
                'quantity_made' => $made,
                'unit_cost' => round((float) $order->total_cost / $made, 4),
                'completed_at' => now(),
            ])->save();

            return $order->fresh(['items', 'costs']);
        });
    }

    /**
     * @param  Collection<int, AssemblyOrderItem>  $lines
     * @param  Collection<int, AssemblyOrderCost>  $costs
     * @param  Collection<int, Item>  $items
     */
    private function build(AssemblyOrder $order, Item $finished, Collection $lines, Collection $costs, Collection $items, float $made, Warehouse $from, Warehouse $to): void
    {
        $needed = [];
        foreach ($lines as $line) {
            $needed[$line->item_id] = ($needed[$line->item_id] ?? 0) + (float) $line->quantity;
        }
        $this->assertFree($order, $needed, $items, $from, 'items', 'this build');

        foreach ($lines as $line) {
            $line->cost = $this->takeOut($order, $items->get($line->item_id), (float) $line->quantity,
                AssemblyOrderItem::class, $line->id, $from->id, "Used in {$order->order_number}");
            $line->save();
        }
        $costs->each->save();

        $componentsCost = Money::sum($lines->pluck('cost'));
        $extra = Money::sum($costs->pluck('amount'));
        $total = Money::add($componentsCost, $extra);
        $this->putIn($order, $finished, $made, $total, $to->id, "Made in {$order->order_number}");

        $order->components_cost = $componentsCost;
        $order->extra_cost = $extra;
        $order->total_cost = $total;

        $this->postExtraCosts($order, $finished, $costs, $made);
    }

    /**
     * Dr Inventory, Cr each cost line's account, for the extra costs now
     * part of the finished goods.
     *
     * @param  Collection<int, AssemblyOrderCost>  $costs
     */
    private function postExtraCosts(AssemblyOrder $order, Item $finished, Collection $costs, float $made): void
    {
        $total = Money::sum($costs->pluck('amount'));
        if ($total <= 0) {
            return;
        }
        $tenantId = (int) $order->tenant_id;
        $default = AccountCodeService::resolve($tenantId, 'production_costs_applied');
        $codes = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereIn('id', $costs->pluck('account_id')->filter())->pluck('account_code', 'id');

        $what = "{$this->qty($made)} {$finished->name}";
        $lines = [[AccountCodeService::resolve($tenantId, 'inventory'), $total, 0.0, "Extra costs of {$what} ({$order->order_number})"]];
        foreach ($costs as $cost) {
            if ((float) $cost->amount > 0) {
                $lines[] = [$codes[$cost->account_id] ?? $default, 0.0, (float) $cost->amount, "{$cost->description} for {$order->order_number}"];
            }
        }

        $this->journals->postLines($order, $order->order_number, $order->movementDate(), "Assembly {$order->order_number}: extra costs of {$what}", $lines);
    }

    /**
     * Finished items out at their cost; components back in, the cost split
     * by their share of the bill at today's prices (by quantity if those
     * are all nil). The last line takes the kobo left over.
     *
     * @param  Collection<int, AssemblyOrderItem>  $lines
     * @param  Collection<int, Item>  $items
     */
    private function breakDown(AssemblyOrder $order, Item $finished, Collection $lines, Collection $items, float $quantity, Warehouse $from, Warehouse $to): void
    {
        $this->assertFree($order, [$finished->id => $quantity], collect([$finished->id => $finished]), $from, 'quantity_made', 'this break-down');
        $cost = $this->takeOut($order, $finished, $quantity, AssemblyOrder::class, $order->id, $from->id, "Broken down in {$order->order_number}");

        $weights = [];
        foreach ($lines as $line) {
            $weights[$line->id] = (float) $line->quantity * BillOfMaterial::currentUnitCost($items->get($line->item_id), $to->id);
        }
        if (array_sum($weights) <= 0) {
            $weights = $lines->mapWithKeys(fn ($l) => [$l->id => (float) $l->quantity])->all();
        }
        $sum = array_sum($weights);
        $lastId = $lines->filter(fn ($l) => (float) $l->quantity > 0)->last()?->id;
        $left = $cost;

        foreach ($lines as $line) {
            $share = $line->id === $lastId ? $left : Money::round($cost * $weights[$line->id] / $sum);
            if ((float) $line->quantity <= 0) {
                $share = 0.0;
            }
            $left = Money::subtract($left, $share);
            $this->putIn($order, $items->get($line->item_id), (float) $line->quantity, $share, $to->id, "Back from break-down {$order->order_number}");
            $line->cost = $share;
            $line->save();
        }

        $order->components_cost = $cost;
        $order->extra_cost = 0;
        $order->total_cost = $cost;
    }

    private function number(mixed $value, float $default, string $key, string $what): float
    {
        if ($value === null || $value === '') {
            return round($default, 4);
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw ValidationException::withMessages([$key => "Enter a number of 0 or more for {$what}."]);
        }

        return round((float) $value, 4);
    }
}
