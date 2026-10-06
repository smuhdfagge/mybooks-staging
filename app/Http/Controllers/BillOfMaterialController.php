<?php

namespace App\Http\Controllers;

use App\Actions\Assembly\DeleteBillOfMaterial;
use App\Actions\Assembly\SaveBillOfMaterial;
use App\Http\Requests\SaveBillOfMaterialRequest;
use App\Models\BillOfMaterial;
use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\Item;
use App\Services\AccountCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bills of materials (session 14): what goes into a batch of a finished
 * item. The work is in App\Actions\Assembly. Assembly orders have their
 * own controller (the old assembly methods here rendered views that never
 * existed).
 */
class BillOfMaterialController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $boms = BillOfMaterial::with(['item', 'components', 'costs'])
            ->withCount('assemblyOrders')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('inventory.bom.index', compact('boms', 'search'));
    }

    public function create(Request $request)
    {
        $bom = new BillOfMaterial(['output_quantity' => 1, 'is_active' => true]);
        // ?item= from an item's page.
        $finished = Item::where('track_inventory', true)->find($request->integer('item'));
        if ($finished) {
            $bom->item_id = $finished->id;
            $bom->name = $finished->name;
        }

        return view('inventory.bom.create', $this->formData($bom, $finished));
    }

    public function store(SaveBillOfMaterialRequest $request, SaveBillOfMaterial $save)
    {
        $bom = $save->create(auth()->user()->tenant_id, $request->validated());

        return redirect()->route('bill-of-materials.show', $bom)->with('success', "Bill of materials \"{$bom->label()}\" saved.");
    }

    public function show(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->load(['item', 'components.item', 'costs.account']);
        $estimate = $billOfMaterial->estimate();
        $orders = $billOfMaterial->assemblyOrders()->latest('id')->limit(10)->get();

        return view('inventory.bom.show', ['bom' => $billOfMaterial, 'estimate' => $estimate, 'orders' => $orders]);
    }

    public function edit(BillOfMaterial $billOfMaterial)
    {
        $billOfMaterial->load(['item', 'components.item', 'costs']);

        return view('inventory.bom.edit', $this->formData($billOfMaterial, $billOfMaterial->item));
    }

    public function update(SaveBillOfMaterialRequest $request, BillOfMaterial $billOfMaterial, SaveBillOfMaterial $save)
    {
        $bom = $save->update($billOfMaterial, $request->validated());

        return redirect()->route('bill-of-materials.show', $bom)->with('success', "Bill of materials \"{$bom->label()}\" updated.");
    }

    public function destroy(BillOfMaterial $billOfMaterial, DeleteBillOfMaterial $delete)
    {
        if ($reason = $delete->blockedBecause($billOfMaterial)) {
            return redirect()->back()->with('error', $reason);
        }
        $delete->handle($billOfMaterial);

        return redirect()->route('bill-of-materials.index')->with('success', 'Bill of materials deleted.');
    }

    /**
     * Stock items to pick on the forms, with their unit and current cost,
     * and how many are free in a warehouse when one is given.
     */
    public function items(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $q = trim((string) $request->query('q', ''));
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        $warehouseId = (int) $request->query('warehouse_id');

        $items = Item::where('tenant_id', $tenantId)->where('track_inventory', true)
            ->when($ids, fn ($query) => $query->whereIn('id', $ids), fn ($query) => $query->where('is_active', true))
            ->when($q !== '' && ! $ids, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderBy('name')->limit(20)->get();
        $free = $warehouseId ? Inventory::where('warehouse_id', $warehouseId)->whereIn('item_id', $items->pluck('id'))->get()
            ->mapWithKeys(fn (Inventory $row) => [(int) $row->item_id => max(0, round((float) $row->quantity - (float) $row->reserved_quantity, 4))]) : collect();

        return response()->json(['data' => $items->map(fn (Item $i) => [
            'id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'unit' => $i->unit,
            'cost' => round(BillOfMaterial::currentUnitCost($i), 4), 'free' => $free[$i->id] ?? 0,
        ])->values()]);
    }

    /** @return array<string, mixed> */
    private function formData(BillOfMaterial $bom, ?Item $finished): array
    {
        $tenantId = auth()->user()->tenant_id;
        $inventoryCode = AccountCodeService::resolve($tenantId, 'inventory');
        $accounts = ChartOfAccount::where('is_active', true)->where('account_code', '!=', $inventoryCode)
            ->orderBy('account_code')->get(['id', 'account_code', 'name']);
        $defaultAccount = SaveBillOfMaterial::defaultCostAccount($tenantId);

        $components = $bom->exists ? $bom->components->map(fn ($c) => [
            'item_id' => (string) $c->item_id,
            'itemSearch' => $c->item->name ?? '',
            'unit' => $c->item->unit ?? '',
            'cost' => $c->item ? round(BillOfMaterial::currentUnitCost($c->item), 4) : 0,
            'quantity' => (float) $c->quantity,
            'waste_percentage' => (float) $c->waste_percentage,
        ])->all() : [];
        $costs = $bom->exists ? $bom->costs->map(fn ($c) => [
            'description' => $c->description, 'amount' => (float) $c->amount, 'account_id' => (string) $c->account_id,
        ])->all() : [];

        return [
            'bom' => $bom,
            'finished' => $finished,
            'accounts' => $accounts,
            'defaultAccountId' => $defaultAccount?->id,
            'components' => $components,
            'costs' => $costs,
        ];
    }
}
