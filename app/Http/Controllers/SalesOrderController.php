<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function index()
    {
        return view('sales-orders.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $orderNumber = SalesOrder::generateNumber(auth()->user()->tenant_id);
        
        return view('sales-orders.create', compact('customers', 'items', 'orderNumber'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        
        $order = DB::transaction(function () use ($tenantId, $validated) {
            $order = SalesOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'],
                'order_number' => SalesOrder::generateNumber($tenantId),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $itemData) {
                $taxRate = $itemData['tax_rate'] ?? 0;
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                $taxAmount = $lineTotal * ($taxRate / 100);

                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal + $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;
            }

            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);

            return $order;
        });

        return redirect()->route('sales-orders.show', $order)->with('success', 'Sales order created.');
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.item']);
        return view('sales-orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $salesOrder->load('items');
        
        return view('sales-orders.edit', compact('salesOrder', 'customers', 'items'));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        // Similar to store logic
        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function destroy(SalesOrder $salesOrder)
    {
        DB::transaction(function () use ($salesOrder) {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        return redirect()->route('sales-orders.index')->with('success', 'Sales order deleted.');
    }

    public function confirm(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'draft') {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', 'Only draft orders can be confirmed.');
        }

        $salesOrder->update(['status' => 'confirmed']);
        
        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', 'Sales order confirmed successfully.');
    }

    public function convertToInvoice(SalesOrder $salesOrder)
    {
        // Logic to convert sales order to invoice
        return redirect()->route('invoices.create', ['sales_order_id' => $salesOrder->id]);
    }
}
