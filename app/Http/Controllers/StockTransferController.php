<?php

namespace App\Http\Controllers;

use App\Actions\StockTransfers\CancelStockTransfer;
use App\Actions\StockTransfers\DeleteStockTransfer;
use App\Actions\StockTransfers\ReceiveStockTransfer;
use App\Actions\StockTransfers\SaveStockTransfer;
use App\Actions\StockTransfers\ShipStockTransfer;
use App\Actions\StockTransfers\TransferNow;
use App\Http\Requests\SaveStockTransferRequest;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Journal;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Stock transfers between warehouses (session 13). The work is in
 * App\Actions\StockTransfers, shared with the API.
 */
class StockTransferController extends Controller
{
    public function index()
    {
        return view('inventory.transfers.index');
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $warehouses = $this->warehouses($tenantId);
        // ?from=&to= (e.g. "Transfer goods back"), else from the default warehouse.
        $from = $warehouses->firstWhere('id', $request->integer('from')) ?? $warehouses->firstWhere('is_default', true) ?? $warehouses->first();
        $to = $warehouses->firstWhere('id', $request->integer('to')) ?? $warehouses->first(fn ($w) => $w->id !== $from?->id);
        $transfer = new StockTransfer([
            'transfer_date' => now(),
            'from_warehouse_id' => $from?->id,
            'to_warehouse_id' => $to?->id,
        ]);
        $number = StockTransfer::previewNumber($tenantId);
        $lines = [];

        return view('inventory.transfers.create', compact('transfer', 'warehouses', 'number', 'lines'));
    }

    public function store(SaveStockTransferRequest $request, SaveStockTransfer $save, ShipStockTransfer $ship, TransferNow $now)
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'transfer_now';

        // All or nothing: a transfer that can't be shipped isn't kept as a draft.
        $transfer = DB::transaction(function () use ($save, $ship, $now, $data, $action) {
            $transfer = $save->create(auth()->user()->tenant_id, $data, auth()->id());

            return match ($action) {
                'transfer_now' => $now->handle($transfer),
                'ship' => $ship->handle($transfer),
                default => $transfer,
            };
        });

        return redirect()->route('stock-transfers.show', $transfer)->with('success', $this->doneMessage($transfer, $action));
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.item', 'createdBy']);
        $lossJournal = Journal::where('reference_type', StockTransfer::class)->where('reference_id', $stockTransfer->id)->first();

        return view('inventory.transfers.show', ['transfer' => $stockTransfer, 'lossJournal' => $lossJournal]);
    }

    public function edit(StockTransfer $stockTransfer)
    {
        if (! $stockTransfer->isDraft()) {
            return redirect()->route('stock-transfers.show', $stockTransfer)->with('error', 'Only a draft transfer can be changed.');
        }
        $stockTransfer->load('items.item');
        $warehouses = $this->warehouses($stockTransfer->tenant_id);
        $free = $this->freeIn((int) $stockTransfer->from_warehouse_id, $stockTransfer->items->pluck('item_id')->all());
        $lines = $stockTransfer->items->map(fn ($l) => [
            'item_id' => (string) $l->item_id,
            'itemSearch' => $l->item->name ?? '',
            'quantity' => (float) $l->quantity,
            'free' => $free[$l->item_id] ?? 0,
        ])->all();

        return view('inventory.transfers.edit', ['transfer' => $stockTransfer, 'warehouses' => $warehouses, 'number' => $stockTransfer->transfer_number, 'lines' => $lines]);
    }

    public function update(SaveStockTransferRequest $request, StockTransfer $stockTransfer, SaveStockTransfer $save, ShipStockTransfer $ship, TransferNow $now)
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'draft';

        $transfer = DB::transaction(function () use ($save, $ship, $now, $data, $action, $stockTransfer) {
            $transfer = $save->update($stockTransfer, $data);

            return match ($action) {
                'transfer_now' => $now->handle($transfer),
                'ship' => $ship->handle($transfer),
                default => $transfer,
            };
        });

        return redirect()->route('stock-transfers.show', $transfer)->with('success', $this->doneMessage($transfer, $action));
    }

    public function ship(StockTransfer $stockTransfer, ShipStockTransfer $ship)
    {
        $transfer = $ship->handle($stockTransfer);

        return redirect()->route('stock-transfers.show', $transfer)->with('success', $this->doneMessage($transfer, 'ship'));
    }

    public function transferNow(StockTransfer $stockTransfer, TransferNow $now)
    {
        $transfer = $now->handle($stockTransfer);

        return redirect()->route('stock-transfers.show', $transfer)->with('success', $this->doneMessage($transfer, 'transfer_now'));
    }

    public function receive(Request $request, StockTransfer $stockTransfer, ReceiveStockTransfer $receive)
    {
        $validated = $request->validate([
            'received_date' => ['nullable', 'date'],
            'shortfall' => ['nullable', Rule::in([ReceiveStockTransfer::RETURN, ReceiveStockTransfer::LOST])],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', Rule::exists('stock_transfer_items', 'id')->where('stock_transfer_id', $stockTransfer->id)],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.*.id.exists' => 'That line is not on this transfer.',
        ]);

        $received = collect($validated['items'] ?? [])->mapWithKeys(fn ($l) => [(int) $l['id'] => $l['quantity_received'] ?? null])->all();
        $transfer = $receive->handle($stockTransfer, $received, $validated['shortfall'] ?? ReceiveStockTransfer::RETURN, $validated['received_date'] ?? null);

        $lost = (float) $transfer->items->sum('quantity_lost');
        $back = (float) $transfer->items->sum('quantity_returned');
        $message = "Transfer {$transfer->transfer_number} received.";
        if ($back > 0) {
            $message .= ' What did not arrive is back in '.$transfer->fromWarehouse->name.'.';
        }
        if ($lost > 0) {
            $message .= ' What did not arrive is recorded as lost (Stock Losses).';
        }

        return redirect()->route('stock-transfers.show', $transfer)->with('success', $message);
    }

    public function cancel(StockTransfer $stockTransfer, CancelStockTransfer $cancel)
    {
        if ($reason = $cancel->blockedBecause($stockTransfer)) {
            return redirect()->back()->with('error', $reason);
        }
        $transfer = $cancel->handle($stockTransfer);

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->transfer_number} cancelled. The goods are back in {$transfer->fromWarehouse->name}.");
    }

    public function destroy(StockTransfer $stockTransfer, DeleteStockTransfer $delete)
    {
        if ($reason = $delete->blockedBecause($stockTransfer)) {
            return redirect()->back()->with('error', $reason);
        }
        $delete->handle($stockTransfer);

        return redirect()->route('stock-transfers.index')->with('success', 'Draft transfer deleted.');
    }

    public function print(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.item']);
        $tenant = auth()->user()->tenant;

        return view('inventory.transfers.print', ['transfer' => $stockTransfer, 'tenant' => $tenant]);
    }

    /**
     * Stock items to pick on the form, with how many are free in the
     * chosen source warehouse.
     */
    public function items(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $warehouseId = Warehouse::whereKey((int) $request->query('warehouse_id'))->value('id');
        $q = trim((string) $request->query('q', ''));
        // ids: refresh the free stock of the lines already on the form.
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));

        $items = Item::where('tenant_id', $tenantId)->where('track_inventory', true)
            ->when($ids, fn ($query) => $query->whereIn('id', $ids), fn ($query) => $query->where('is_active', true))
            ->when($q !== '' && ! $ids, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'unit']);
        $free = $warehouseId ? $this->freeIn((int) $warehouseId, $items->pluck('id')->all()) : [];

        return response()->json(['data' => $items->map(fn (Item $i) => [
            'id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'unit' => $i->unit, 'free' => $free[$i->id] ?? 0,
        ])->values()]);
    }

    /**
     * Free stock (on hand less reserved) per item in one warehouse.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, float>
     */
    private function freeIn(int $warehouseId, array $itemIds): array
    {
        return Inventory::where('warehouse_id', $warehouseId)->whereIn('item_id', $itemIds)->get()
            ->mapWithKeys(fn (Inventory $row) => [(int) $row->item_id => max(0, round((float) $row->quantity - (float) $row->reserved_quantity, 4))])
            ->all();
    }

    private function warehouses(int $tenantId)
    {
        return Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }

    private function doneMessage(StockTransfer $transfer, string $action): string
    {
        $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);

        return match ($action) {
            'transfer_now' => "Transfer {$transfer->transfer_number} done: the goods are now in {$transfer->toWarehouse->name}.",
            'ship' => "Transfer {$transfer->transfer_number} shipped. The goods are on the road; receive them when they reach {$transfer->toWarehouse->name}.",
            default => "Draft transfer {$transfer->transfer_number} saved. Nothing has moved yet.",
        };
    }
}
