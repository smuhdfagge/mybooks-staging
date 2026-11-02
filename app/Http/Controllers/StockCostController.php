<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\UncostedStock;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Missing Stock Costs: stock on hand with no recorded cost, which an
 * invoice can't take out until someone records what it cost (audit MB-31).
 * The same work as stock:reconcile-layers, one item and warehouse at a time.
 */
class StockCostController extends Controller
{
    public function index(UncostedStock $uncosted)
    {
        $rows = collect($uncosted->find((int) auth()->user()->tenant_id));

        return view('inventory.costs', [
            'missing' => $rows->filter(fn ($row) => $row['missing'] > 0)->values(),
            'excess' => $rows->filter(fn ($row) => $row['missing'] < 0)->values(),
        ]);
    }

    public function store(Request $request, UncostedStock $uncosted)
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $validated = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'unit_cost' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        try {
            $recorded = $uncosted->record($tenantId, (int) $validated['item_id'], (int) $validated['warehouse_id'], (float) $validated['unit_cost']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            // A closed or locked month: the journal can't be dated.
            return back()->with('error', implode(' ', $e->validator->errors()->all()));
        }

        $item = Item::find($validated['item_id']);
        $warehouse = Warehouse::find($validated['warehouse_id']);

        return back()->with('success', sprintf('Recorded the cost of %s %s in %s: %s.',
            UncostedStock::quantity($recorded['quantity']), $item?->name, $warehouse?->name, Money::format($recorded['value'])));
    }
}
