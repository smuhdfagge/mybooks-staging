<?php

namespace App\Http\Controllers;

use App\Actions\Assembly\CancelAssemblyOrder;
use App\Actions\Assembly\CompleteAssemblyOrder;
use App\Actions\Assembly\DeleteAssemblyOrder;
use App\Actions\Assembly\SaveAssemblyOrder;
use App\Actions\Assembly\UndoAssemblyOrder;
use App\Http\Requests\SaveAssemblyOrderRequest;
use App\Models\AssemblyOrder;
use App\Models\BillOfMaterial;
use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Warehouse;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Assembly orders (session 14): builds and break-downs from a bill of
 * materials. The work is in App\Actions\Assembly, shared with the API.
 */
class AssemblyOrderController extends Controller
{
    public function index()
    {
        return view('inventory.assembly.index');
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $boms = BillOfMaterial::active()->with('item')->orderBy('name')->get();
        $bom = $boms->firstWhere('id', $request->integer('bill')) ?? ($boms->count() === 1 ? $boms->first() : null);
        $kind = $request->query('kind') === AssemblyOrder::KIND_BREAKDOWN ? AssemblyOrder::KIND_BREAKDOWN : AssemblyOrder::KIND_BUILD;
        $order = new AssemblyOrder([
            'kind' => $kind,
            'assembly_date' => now(),
            'bill_of_materials_id' => $bom?->id,
            'planned_quantity' => $bom ? (float) $bom->output_quantity : null,
        ]);
        $number = AssemblyOrder::previewNumber($tenantId);

        return view('inventory.assembly.create', compact('order', 'boms', 'number'));
    }

    public function store(SaveAssemblyOrderRequest $request, SaveAssemblyOrder $save, CompleteAssemblyOrder $complete)
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'complete';
        Warehouse::rememberChoice($data['warehouse_id'] ?? null);

        // All or nothing: an order that can't be completed isn't kept as a draft.
        $order = DB::transaction(function () use ($save, $complete, $data, $action) {
            $order = $save->create(auth()->user()->tenant_id, $data, auth()->id());

            return $action === 'complete' ? $complete->handle($order) : $order;
        });

        return redirect()->route('assembly-orders.show', $order)->with('success', $this->doneMessage($order));
    }

    public function show(AssemblyOrder $assemblyOrder)
    {
        $assemblyOrder->load(['billOfMaterial.item', 'items.item', 'costs.account', 'warehouse', 'toWarehouse', 'createdBy']);
        $journals = Journal::where('reference_type', AssemblyOrder::class)->where('reference_id', $assemblyOrder->id)->orderBy('id')->get();
        $free = $assemblyOrder->isDraft() ? $this->freeIn((int) ($assemblyOrder->warehouse_id ?: Warehouse::defaultIdFor($assemblyOrder->tenant_id)),
            $assemblyOrder->isBreakdown() ? [$assemblyOrder->billOfMaterial?->item_id] : $assemblyOrder->items->pluck('item_id')->all()) : [];

        return view('inventory.assembly.show', ['order' => $assemblyOrder, 'journals' => $journals, 'free' => $free]);
    }

    public function edit(AssemblyOrder $assemblyOrder)
    {
        if (! $assemblyOrder->isDraft()) {
            return redirect()->route('assembly-orders.show', $assemblyOrder)->with('error', 'Only a draft can be changed.');
        }
        $boms = BillOfMaterial::with('item')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $assemblyOrder->bill_of_materials_id))->orderBy('name')->get();

        return view('inventory.assembly.edit', ['order' => $assemblyOrder, 'boms' => $boms, 'number' => $assemblyOrder->order_number]);
    }

    public function update(SaveAssemblyOrderRequest $request, AssemblyOrder $assemblyOrder, SaveAssemblyOrder $save, CompleteAssemblyOrder $complete)
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'draft';

        $order = DB::transaction(function () use ($save, $complete, $data, $action, $assemblyOrder) {
            $order = $save->update($assemblyOrder, $data);

            return $action === 'complete' ? $complete->handle($order) : $order;
        });

        return redirect()->route('assembly-orders.show', $order)->with('success', $this->doneMessage($order));
    }

    /** Complete a draft, with what was really used and made. */
    public function complete(Request $request, AssemblyOrder $assemblyOrder, CompleteAssemblyOrder $complete)
    {
        $validated = $request->validate([
            'quantity_made' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'costs' => ['nullable', 'array'],
            'costs.*.id' => ['required', 'integer'],
            'costs.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $actual = [
            'quantity_made' => $validated['quantity_made'] ?? null,
            'items' => collect($validated['items'] ?? [])->mapWithKeys(fn ($l) => [(int) $l['id'] => $l['quantity'] ?? null])->all(),
            'costs' => collect($validated['costs'] ?? [])->mapWithKeys(fn ($l) => [(int) $l['id'] => $l['amount'] ?? null])->all(),
        ];
        $order = $complete->handle($assemblyOrder, $actual);

        return redirect()->route('assembly-orders.show', $order)->with('success', $this->doneMessage($order));
    }

    public function undo(AssemblyOrder $assemblyOrder, UndoAssemblyOrder $undo)
    {
        if ($reason = $undo->blockedBecause($assemblyOrder)) {
            return redirect()->back()->with('error', $reason);
        }
        $order = $undo->handle($assemblyOrder);

        return redirect()->route('assembly-orders.show', $order)->with('success', $order->isBreakdown()
            ? "{$order->order_number} undone: the items are back together and it is a draft again."
            : "Build {$order->order_number} undone: the components are back in stock at the cost they left at, and it is a draft again.");
    }

    public function cancel(AssemblyOrder $assemblyOrder, CancelAssemblyOrder $cancel)
    {
        if ($reason = $cancel->blockedBecause($assemblyOrder)) {
            return redirect()->back()->with('error', $reason);
        }
        $cancel->handle($assemblyOrder);

        return redirect()->route('assembly-orders.show', $assemblyOrder)->with('success', "{$assemblyOrder->order_number} cancelled.");
    }

    public function destroy(AssemblyOrder $assemblyOrder, DeleteAssemblyOrder $delete)
    {
        if ($reason = $delete->blockedBecause($assemblyOrder)) {
            return redirect()->back()->with('error', $reason);
        }
        $delete->handle($assemblyOrder);

        return redirect()->route('assembly-orders.index')->with('success', "{$assemblyOrder->order_number} deleted.");
    }

    /** Production sheet, A4. */
    public function print(AssemblyOrder $assemblyOrder)
    {
        $assemblyOrder->load(['billOfMaterial.item', 'items.item', 'costs', 'warehouse', 'toWarehouse']);

        return view('inventory.assembly.print', ['order' => $assemblyOrder, 'tenant' => auth()->user()->tenant]);
    }

    /**
     * For the create form: the bill's components with how much one finished
     * unit needs and how much is free in the chosen warehouse.
     */
    public function availability(Request $request): JsonResponse
    {
        $bom = BillOfMaterial::with(['item', 'components.item', 'costs'])->findOrFail($request->integer('bill_of_materials_id'));
        $breakdown = $request->query('kind') === AssemblyOrder::KIND_BREAKDOWN;
        $warehouseId = Warehouse::whereKey($request->integer('warehouse_id'))->value('id') ?? Warehouse::defaultIdFor($bom->tenant_id);
        $output = (float) $bom->output_quantity;
        $estimate = $bom->estimate();

        if ($breakdown) {
            $free = $this->freeIn((int) $warehouseId, [$bom->item_id]);
            $components = [[
                'name' => $bom->item->name ?? '', 'unit' => $bom->item->unit ?? '', 'per_unit' => 1, 'free' => $free[$bom->item_id] ?? 0,
            ]];
            $max = floor($free[$bom->item_id] ?? 0);
        } else {
            $free = $this->freeIn((int) $warehouseId, $bom->components->pluck('item_id')->all());
            $components = $bom->components->map(fn ($c) => [
                'name' => $c->item->name ?? '', 'unit' => $c->item->unit ?? '', 'per_unit' => round($bom->needPerUnit($c), 6), 'free' => $free[$c->item_id] ?? 0,
            ])->values()->all();
            $max = $bom->maxBuildable((int) $warehouseId);
        }

        return response()->json([
            'finished' => ['name' => $bom->item->name ?? '', 'unit' => $bom->item->unit ?? ''],
            'output_quantity' => $output,
            'unit_cost' => $estimate['per_unit'],
            'extra_per_unit' => $output > 0 ? round($estimate['extra'] / $output, 4) : 0,
            'components' => $components,
            'max' => $max ?? 0,
        ]);
    }

    /** What was made (or broken down) per finished item in a period, with its cost. */
    public function report(Request $request)
    {
        $from = $this->date($request->query('from'), now()->startOfMonth());
        $to = $this->date($request->query('to'), now());

        $rows = AssemblyOrder::query()
            ->join('bill_of_materials', 'bill_of_materials.id', '=', 'assembly_orders.bill_of_materials_id')
            ->join('items', 'items.id', '=', 'bill_of_materials.item_id')
            ->where('assembly_orders.status', AssemblyOrder::STATUS_COMPLETED)
            ->whereDate('assembly_orders.assembly_date', '>=', $from->toDateString())
            ->whereDate('assembly_orders.assembly_date', '<=', $to->toDateString())
            ->groupBy('items.id', 'items.name', 'items.unit', 'assembly_orders.kind')
            ->orderBy('assembly_orders.kind')->orderBy('items.name')
            ->selectRaw('items.id as item_id, items.name as item_name, items.unit as unit, assembly_orders.kind as kind, COUNT(*) as orders,
                SUM(assembly_orders.quantity_made) as quantity, SUM(assembly_orders.components_cost) as components_cost,
                SUM(assembly_orders.extra_cost) as extra_cost, SUM(assembly_orders.total_cost) as total_cost')
            ->toBase()->get();

        return view('inventory.assembly.report', compact('rows', 'from', 'to'));
    }

    private function date(mixed $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Free stock (on hand less reserved) per item in one warehouse.
     *
     * @param  array<int, mixed>  $itemIds
     * @return array<int, float>
     */
    private function freeIn(int $warehouseId, array $itemIds): array
    {
        return Inventory::where('warehouse_id', $warehouseId)->whereIn('item_id', array_filter($itemIds))->get()
            ->mapWithKeys(fn (Inventory $row) => [(int) $row->item_id => max(0, round((float) $row->quantity - (float) $row->reserved_quantity, 4))])
            ->all();
    }

    private function doneMessage(AssemblyOrder $order): string
    {
        $order->loadMissing('billOfMaterial.item', 'toWarehouse');
        $name = $order->billOfMaterial->item->name ?? 'items';
        $made = rtrim(rtrim(number_format((float) $order->quantity_made, 4), '0'), '.');

        if (! $order->isCompleted()) {
            return "Draft {$order->order_number} saved. Nothing has moved yet.";
        }

        return $order->isBreakdown()
            ? "{$order->order_number} done: {$made} {$name} broken down and the parts are back in stock."
            : "{$order->order_number} done: {$made} {$name} made, at ".Money::format($order->unit_cost).' each.';
    }
}
