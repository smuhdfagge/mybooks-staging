<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Vendor;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return view('purchase-orders.index');
    }

    public function create()
    {
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $orderNumber = PurchaseOrder::generateNumber(auth()->user()->tenant_id);

        return view('purchase-orders.create', compact('vendors', 'items', 'orderNumber'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $order = DB::transaction(function () use ($tenantId, $validated) {
            $order = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $validated['vendor_id'],
                'order_number' => PurchaseOrder::generateNumber($tenantId),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $itemData) {
                $taxRate = $itemData['tax_rate'] ?? 0;
                $discount = $itemData['discount'] ?? 0;
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                $taxAmount = ($lineTotal - $discount) * ($taxRate / 100);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $discount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal - $discount + $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;
                $totalDiscount += $discount;
            }

            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $totalDiscount,
                'total' => $subtotal - $totalDiscount + $totalTax,
            ]);

            return $order;
        });

        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor', 'items.item', 'createdBy']);
        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if (!in_array($purchaseOrder->status, ['draft'])) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->load('items');
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();

        return view('purchase-orders.edit', compact('purchaseOrder', 'vendors', 'items'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be updated.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            $purchaseOrder->items()->delete();

            foreach ($validated['items'] as $itemData) {
                $taxRate = $itemData['tax_rate'] ?? 0;
                $discount = $itemData['discount'] ?? 0;
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                $taxAmount = ($lineTotal - $discount) * ($taxRate / 100);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $discount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal - $discount + $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;
                $totalDiscount += $discount;
            }

            $purchaseOrder->update([
                'vendor_id' => $validated['vendor_id'],
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $totalDiscount,
                'total' => $subtotal - $totalDiscount + $totalTax,
            ]);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->bills()->exists()) {
            return redirect()->route('purchase-orders.index')
                ->with('error', 'Cannot delete purchase order with associated bills.');
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    public function confirm(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be confirmed.');
        }

        $purchaseOrder->update(['status' => 'confirmed']);

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order confirmed.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if (!in_array($purchaseOrder->status, ['draft', 'confirmed'])) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'This purchase order cannot be cancelled.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order cancelled.');
    }

    public function convertToBill(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_BILLED) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'This purchase order has already been billed.');
        }

        if (!in_array($purchaseOrder->status, PurchaseOrder::BILLABLE, true)) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only confirmed or received purchase orders can be converted to bills.');
        }

        return redirect()->route('bills.create', ['purchase_order_id' => $purchaseOrder->id]);
    }
}
