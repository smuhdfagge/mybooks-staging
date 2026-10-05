<?php

namespace App\Actions\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Models\Item;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating or changing a draft stock transfer (session 13). A draft moves
 * nothing; ShipStockTransfer does. Only stock-tracked items can be moved,
 * between two different warehouses of this business that are in use.
 * The same item on two lines is put on one line.
 *
 * $data keys: transfer_date, from_warehouse_id, to_warehouse_id,
 * reference, notes, items[] (item_id, quantity, notes).
 */
class SaveStockTransfer
{
    use MovesTransferStock;

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): StockTransfer
    {
        [$from, $to] = self::checkWarehouses($tenantId, $data['from_warehouse_id'] ?? null, $data['to_warehouse_id'] ?? null);
        $lines = $this->lines($tenantId, $data['items'] ?? []);

        return DB::transaction(function () use ($tenantId, $data, $userId, $from, $to, $lines) {
            $transfer = new StockTransfer([
                'tenant_id' => $tenantId,
                'transfer_number' => StockTransfer::generateNumber($tenantId),
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'status' => StockTransferStatus::Draft->value,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $transfer->skipTenantGuard = true;
            $transfer->save();
            $transfer->items()->createMany($lines);

            return $transfer->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw ValidationException::withMessages(['transfer' => "Transfer {$transfer->transfer_number} has been shipped, so it can't be changed."]);
        }
        [$from, $to] = self::checkWarehouses($transfer->tenant_id, $data['from_warehouse_id'] ?? null, $data['to_warehouse_id'] ?? null);
        $lines = $this->lines($transfer->tenant_id, $data['items'] ?? []);

        return DB::transaction(function () use ($transfer, $data, $from, $to, $lines) {
            $transfer->update([
                'transfer_date' => $data['transfer_date'] ?? $transfer->transfer_date,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $transfer->items()->delete();
            $transfer->items()->createMany($lines);

            return $transfer->fresh(['items']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @return array<int, array{item_id: int, quantity: float, notes: ?string}>
     */
    private function lines(int $tenantId, array $input): array
    {
        $lines = [];
        foreach (array_values($input) as $index => $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 4);
            if (empty($line['item_id']) && $quantity <= 0) {
                continue; // empty row on the form
            }
            $item = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) ($line['item_id'] ?? 0))->first();
            if (! $item) {
                throw ValidationException::withMessages(["items.{$index}.item_id" => 'Choose an item.']);
            }
            if (! $item->track_inventory) {
                throw ValidationException::withMessages(["items.{$index}.item_id" => "{$item->name} doesn't keep stock, so it can't be transferred."]);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => "Enter how many {$item->name} to send."]);
            }

            if (isset($lines[$item->id])) {
                $lines[$item->id]['quantity'] = round($lines[$item->id]['quantity'] + $quantity, 4);
            } else {
                $lines[$item->id] = ['item_id' => $item->id, 'quantity' => $quantity, 'notes' => $line['notes'] ?? null];
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item to send.']);
        }

        return array_values($lines);
    }
}
