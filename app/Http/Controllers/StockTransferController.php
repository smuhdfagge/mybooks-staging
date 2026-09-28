<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->latest()
            ->paginate(20);

        return view('inventory.transfers.index', compact('transfers'));
    }

    public function create()
    {
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $items = Item::where('track_inventory', true)->active()->orderBy('name')->get();

        return view('inventory.transfers.create', compact('warehouses', 'items'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'to_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', auth()->user()->tenant_id), 'different:from_warehouse_id'],
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.notes' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $transfer = DB::transaction(function () use ($validated, $tenantId) {
            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'transfer_number' => StockTransfer::generateNumber($tenantId),
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $transfer->items()->create($item);
            }

            return $transfer;
        });

        return redirect()->route('stock-transfers.show', $transfer)
            ->with('success', 'Stock transfer created successfully.');
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.item', 'createdBy']);

        return view('inventory.transfers.show', compact('stockTransfer'));
    }

    public function ship(StockTransfer $stockTransfer)
    {
        DB::transaction(function () use ($stockTransfer) {
            $stockTransfer->ship();
        });

        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('success', 'Stock transfer shipped successfully.');
    }

    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => [Rule::exists('stock_transfer_items', 'id')->where('stock_transfer_id', $stockTransfer->id)],
            'items.*.quantity_received' => 'numeric|min:0',
        ]);

        DB::transaction(function () use ($stockTransfer, $validated) {
            // Update received quantities if provided
            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    StockTransferItem::where('id', $itemData['id'])
                        ->where('stock_transfer_id', $stockTransfer->id)
                        ->update(['quantity_received' => $itemData['quantity_received']]);
                }
                $stockTransfer->refresh();
            }

            $stockTransfer->receive();
        });

        return redirect()->route('stock-transfers.show', $stockTransfer)
            ->with('success', 'Stock transfer received successfully.');
    }

    public function destroy(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== StockTransfer::STATUS_DRAFT) {
            return redirect()->back()->with('error', 'Only draft transfers can be deleted.');
        }

        $stockTransfer->items()->delete();
        $stockTransfer->delete();

        return redirect()->route('stock-transfers.index')
            ->with('success', 'Stock transfer deleted successfully.');
    }
}
