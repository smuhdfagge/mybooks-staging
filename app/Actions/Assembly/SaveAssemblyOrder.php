<?php

namespace App\Actions\Assembly;

use App\Enums\AssemblyOrderStatus;
use App\Models\AssemblyOrder;
use App\Models\AssemblyOrderCost;
use App\Models\AssemblyOrderItem;
use App\Models\BillOfMaterial;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating or changing a draft assembly order (session 14). A draft moves
 * nothing; CompleteAssemblyOrder does.
 *
 * The quantity is in finished units (80 bags), not batches; the bill's
 * per-batch quantities are scaled by quantity / batch size. A build plans
 * each component with its wastage and the bill's extra costs; a break-down
 * plans the components you get back (no wastage, no extra costs).
 *
 * The warehouses default to the business's default warehouse (and to each
 * other), so a business with one warehouse never sees them.
 *
 * $data keys: kind (build|breakdown), bill_of_materials_id, assembly_date,
 * quantity, warehouse_id, to_warehouse_id, notes.
 */
class SaveAssemblyOrder
{
    use MovesAssemblyStock;

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): AssemblyOrder
    {
        [$bom, $kind, $quantity, $from, $to] = $this->check($tenantId, $data);

        return DB::transaction(function () use ($tenantId, $data, $userId, $bom, $kind, $quantity, $from, $to) {
            $order = new AssemblyOrder([
                'tenant_id' => $tenantId,
                'order_number' => AssemblyOrder::generateNumber($tenantId),
                'kind' => $kind,
                'assembly_date' => $data['assembly_date'] ?? now()->toDateString(),
                'bill_of_materials_id' => $bom->id,
                'warehouse_id' => $from,
                'to_warehouse_id' => $to,
                'quantity' => round($quantity / (float) $bom->output_quantity, 4),
                'planned_quantity' => $quantity,
                'status' => AssemblyOrderStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $order->skipTenantGuard = true;
            $order->save();
            $this->planLines($order, $bom);

            return $order->fresh(['items', 'costs']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(AssemblyOrder $order, array $data): AssemblyOrder
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages(['order' => "{$order->order_number} is {$order->status}, so it can't be changed."]);
        }
        [$bom, $kind, $quantity, $from, $to] = $this->check((int) $order->tenant_id, $data + ['kind' => $order->kind], $order);

        return DB::transaction(function () use ($order, $data, $bom, $kind, $quantity, $from, $to) {
            $order->update([
                'kind' => $kind,
                'assembly_date' => $data['assembly_date'] ?? $order->assembly_date,
                'bill_of_materials_id' => $bom->id,
                'warehouse_id' => $from,
                'to_warehouse_id' => $to,
                'quantity' => round($quantity / (float) $bom->output_quantity, 4),
                'planned_quantity' => $quantity,
                'notes' => $data['notes'] ?? null,
            ]);
            $order->items()->delete();
            $order->costs()->delete();
            $this->planLines($order, $bom);

            return $order->fresh(['items', 'costs']);
        });
    }

    /** Lines planned from the bill, scaled to the quantity (also used for old drafts without lines). */
    public function planLines(AssemblyOrder $order, BillOfMaterial $bom): void
    {
        $bom->load(['components', 'costs']);
        $batches = $order->plannedQuantity() / (float) $bom->output_quantity;

        foreach ($bom->components as $component) {
            $perBatch = $order->isBreakdown() ? (float) $component->quantity : $component->effective_quantity;
            $line = new AssemblyOrderItem([
                'tenant_id' => $order->tenant_id,
                'assembly_order_id' => $order->id,
                'item_id' => $component->item_id,
                'bom_item_id' => $component->id,
                'planned_quantity' => round($perBatch * $batches, 4),
            ]);
            $line->skipTenantGuard = true;
            $line->save();
        }

        if ($order->isBreakdown()) {
            return;
        }
        foreach ($bom->costs as $cost) {
            $line = new AssemblyOrderCost([
                'tenant_id' => $order->tenant_id,
                'assembly_order_id' => $order->id,
                'description' => $cost->description,
                'account_id' => $cost->account_id,
                'planned_amount' => round((float) $cost->amount * $batches, 2),
            ]);
            $line->skipTenantGuard = true;
            $line->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: BillOfMaterial, 1: string, 2: float, 3: int, 4: int}
     */
    private function check(int $tenantId, array $data, ?AssemblyOrder $order = null): array
    {
        $kind = ($data['kind'] ?? AssemblyOrder::KIND_BUILD) === AssemblyOrder::KIND_BREAKDOWN ? AssemblyOrder::KIND_BREAKDOWN : AssemblyOrder::KIND_BUILD;

        $bom = BillOfMaterial::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) ($data['bill_of_materials_id'] ?? 0))->first();
        if (! $bom) {
            throw ValidationException::withMessages(['bill_of_materials_id' => 'Choose one of your bills of materials.']);
        }
        $unchanged = $order && (int) $order->bill_of_materials_id === $bom->id;
        if (! $bom->is_active && ! $unchanged) {
            throw ValidationException::withMessages(['bill_of_materials_id' => "{$bom->label()} is not in use any more."]);
        }
        if ((float) $bom->output_quantity <= 0 || ! $bom->components()->exists()) {
            throw ValidationException::withMessages(['bill_of_materials_id' => "{$bom->label()} has no components yet."]);
        }

        $quantity = round((float) ($data['quantity'] ?? 0), 4);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => $kind === AssemblyOrder::KIND_BREAKDOWN ? 'Enter how many to break down.' : 'Enter how many to make.']);
        }

        $from = Warehouse::resolveIdFor($tenantId, $data['warehouse_id'] ?? null);
        $to = ($data['to_warehouse_id'] ?? null) ? Warehouse::resolveIdFor($tenantId, $data['to_warehouse_id']) : $from;
        self::ownWarehouse($tenantId, $from, 'warehouse_id');
        self::ownWarehouse($tenantId, $to, 'to_warehouse_id');

        return [$bom, $kind, $quantity, $from, $to];
    }
}
