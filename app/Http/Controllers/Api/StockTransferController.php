<?php

namespace App\Http\Controllers\Api;

use App\Actions\StockTransfers\SaveStockTransfer;
use App\Actions\StockTransfers\ShipStockTransfer;
use App\Actions\StockTransfers\TransferNow;
use App\Http\Requests\SaveStockTransferRequest;
use App\Models\StockTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock transfers over the API (session 13): list, read, and create, with
 * "ship": true to ship it, or "receive": true to ship and receive it in
 * one call (transfer now). Same actions as the web.
 */
class StockTransferController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(StockTransfer::moduleOn(), 404);

        $transfers = StockTransfer::with(['items', 'fromWarehouse:id,name', 'toWarehouse:id,name'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when((int) $request->input('warehouse_id'), fn ($q, $id) => $q->where(fn ($w) => $w->where('from_warehouse_id', $id)->orWhere('to_warehouse_id', $id)))
            ->orderByDesc('transfer_date')->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        $transfers->setCollection($transfers->getCollection()->map(fn ($t) => $this->present($t)));

        return $this->paginated($transfers);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        abort_unless(StockTransfer::moduleOn(), 404);

        return $this->success($this->present($stockTransfer->load(['items', 'fromWarehouse:id,name', 'toWarehouse:id,name'])));
    }

    public function store(SaveStockTransferRequest $request, SaveStockTransfer $save, ShipStockTransfer $ship, TransferNow $now): JsonResponse
    {
        abort_unless(StockTransfer::moduleOn(), 404);
        $data = $request->validated();

        $transfer = DB::transaction(function () use ($request, $data, $save, $ship, $now) {
            $transfer = $save->create($this->getTenantId(), $data, auth()->id());
            if ($request->boolean('receive')) {
                return $now->handle($transfer);
            }

            return $request->boolean('ship') ? $ship->handle($transfer) : $transfer;
        });

        return $this->created($this->present($transfer->load(['items', 'fromWarehouse:id,name', 'toWarehouse:id,name'])));
    }

    /** @return array<string, mixed> */
    private function present(StockTransfer $t): array
    {
        return [
            'id' => $t->id,
            'transfer_number' => $t->transfer_number,
            'transfer_date' => $t->transfer_date?->toDateString(),
            'status' => $t->status,
            'from_warehouse' => ['id' => $t->from_warehouse_id, 'name' => $t->fromWarehouse?->name],
            'to_warehouse' => ['id' => $t->to_warehouse_id, 'name' => $t->toWarehouse?->name],
            'reference' => $t->reference,
            'notes' => $t->notes,
            'received_date' => $t->received_date?->toDateString(),
            'shipped_cost' => $t->shippedCost(),
            'items' => $t->items->map(fn ($l) => [
                'id' => $l->id,
                'item_id' => $l->item_id,
                'quantity' => (float) $l->quantity,
                'quantity_received' => (float) $l->quantity_received,
                'quantity_returned' => (float) $l->quantity_returned,
                'quantity_lost' => (float) $l->quantity_lost,
                'shipped_cost' => (float) $l->shipped_cost,
                'lost_cost' => (float) $l->lost_cost,
            ])->values(),
        ];
    }
}
