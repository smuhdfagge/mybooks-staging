<?php

namespace App\Http\Controllers\Api;

use App\Actions\Assembly\CompleteAssemblyOrder;
use App\Actions\Assembly\SaveAssemblyOrder;
use App\Http\Requests\SaveAssemblyOrderRequest;
use App\Models\AssemblyOrder;
use App\Models\BillOfMaterial;
use App\Models\BomCost;
use App\Models\BomItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Assembly over the API (session 14): list bills of materials, list and
 * read assembly orders, and create one, with "complete": true to complete
 * it at once (optionally with "quantity_made"). Same actions as the web.
 */
class AssemblyOrderController extends BaseApiController
{
    public function bills(): JsonResponse
    {
        abort_unless(AssemblyOrder::moduleOn(), 404);

        $bills = BillOfMaterial::with(['components', 'costs'])->orderBy('name')->get()->map(fn (BillOfMaterial $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'version' => $b->version,
            'item_id' => $b->item_id,
            'output_quantity' => (float) $b->output_quantity,
            'is_active' => $b->is_active,
            'components' => $b->components->map(fn (BomItem $c) => [
                'item_id' => $c->item_id, 'quantity' => (float) $c->quantity, 'waste_percentage' => (float) $c->waste_percentage,
            ])->values(),
            'extra_costs' => $b->costs->map(fn (BomCost $c) => [
                'description' => $c->description, 'amount' => (float) $c->amount, 'account_id' => $c->account_id,
            ])->values(),
        ]);

        return $this->success($bills);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(AssemblyOrder::moduleOn(), 404);

        $orders = AssemblyOrder::with(['items', 'costs', 'billOfMaterial:id,item_id,name'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->orderByDesc('assembly_date')->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        $orders->setCollection($orders->getCollection()->map(fn ($o) => $this->present($o)));

        return $this->paginated($orders);
    }

    public function show(AssemblyOrder $assemblyOrder): JsonResponse
    {
        abort_unless(AssemblyOrder::moduleOn(), 404);

        return $this->success($this->present($assemblyOrder->load(['items', 'costs', 'billOfMaterial:id,item_id,name'])));
    }

    public function store(SaveAssemblyOrderRequest $request, SaveAssemblyOrder $save, CompleteAssemblyOrder $complete): JsonResponse
    {
        abort_unless(AssemblyOrder::moduleOn(), 404);
        $data = $request->validated();
        $request->validate(['quantity_made' => ['nullable', 'numeric', 'gt:0']]);

        $order = DB::transaction(function () use ($request, $data, $save, $complete) {
            $order = $save->create($this->getTenantId(), $data, auth()->id());

            return $request->boolean('complete')
                ? $complete->handle($order, ['quantity_made' => $request->input('quantity_made')])
                : $order;
        });

        return $this->created($this->present($order->load(['items', 'costs', 'billOfMaterial:id,item_id,name'])));
    }

    /** @return array<string, mixed> */
    private function present(AssemblyOrder $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'kind' => $o->kind,
            'assembly_date' => $o->assembly_date?->toDateString(),
            'status' => $o->status,
            'bill_of_materials_id' => $o->bill_of_materials_id,
            'item_id' => $o->billOfMaterial?->item_id,
            'warehouse_id' => $o->warehouse_id,
            'to_warehouse_id' => $o->to_warehouse_id,
            'planned_quantity' => $o->plannedQuantity(),
            'quantity_made' => $o->quantity_made !== null ? (float) $o->quantity_made : null,
            'components_cost' => (float) $o->components_cost,
            'extra_cost' => (float) $o->extra_cost,
            'total_cost' => (float) $o->total_cost,
            'unit_cost' => (float) $o->unit_cost,
            'notes' => $o->notes,
            'items' => $o->items->map(fn ($l) => [
                'id' => $l->id,
                'item_id' => $l->item_id,
                'planned_quantity' => (float) $l->planned_quantity,
                'quantity' => $l->quantity !== null ? (float) $l->quantity : null,
                'cost' => (float) $l->cost,
            ])->values(),
            'extra_costs' => $o->costs->map(fn ($c) => [
                'id' => $c->id,
                'description' => $c->description,
                'account_id' => $c->account_id,
                'planned_amount' => (float) $c->planned_amount,
                'amount' => $c->amount !== null ? (float) $c->amount : null,
            ])->values(),
        ];
    }
}
