<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

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
        $orderNumber = SalesOrder::previewNumber(auth()->user()->tenant_id);

        return view('sales-orders.create', compact('customers', 'items', 'orderNumber'));
    }

    public function store(\App\Http\Requests\StoreSalesOrderRequest $request, \App\Actions\SalesOrders\SaveSalesOrder $save)
    {
        // Same rules as the API (R3).
        $order = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('sales-orders.show', $order)->with('success', 'Sales order created.');
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.item']);

        return view('sales-orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, \App\Actions\SalesOrders\SaveSalesOrder::EDITABLE, true)) {
            return redirect()->route('sales-orders.show', $salesOrder)->with('error', "A {$salesOrder->status} sales order can't be changed.");
        }

        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $salesOrder->load('items');

        return view('sales-orders.edit', compact('salesOrder', 'customers', 'items'));
    }

    public function update(\App\Http\Requests\UpdateSalesOrderRequest $request, SalesOrder $salesOrder, \App\Actions\SalesOrders\SaveSalesOrder $save)
    {
        // This used to save nothing at all (R3).
        $save->update($salesOrder, $request->validated());

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function destroy(SalesOrder $salesOrder, \App\Actions\SalesOrders\DeleteSalesOrder $delete)
    {
        // Same rules as the API and the bulk delete (R3).
        if ($reason = $delete->blockedBecause($salesOrder)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($salesOrder);

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

    public function convertToInvoice(SalesOrder $salesOrder, \App\Actions\Invoices\SaveInvoice $save)
    {
        if (! in_array($salesOrder->status, ['confirmed', 'processing'])) {
            return redirect()->back()->with('error', 'Only confirmed or processing orders can be converted to invoices.');
        }

        $salesOrder->load('items');

        // Lines still to deliver.
        $lines = [];
        foreach ($salesOrder->items as $soItem) {
            $qty = $soItem->quantity - ($soItem->quantity_fulfilled ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'item_id' => $soItem->item_id,
                'description' => $soItem->description,
                'quantity' => $qty,
                'unit_price' => $soItem->unit_price,
                // The line discount is money for the whole line; invoice the part still to deliver.
                'discount' => (float) $soItem->quantity > 0 ? round((float) $soItem->discount * $qty / (float) $soItem->quantity, 2) : 0,
                'discount_type' => 'fixed',
                'tax_rate' => $soItem->tax_rate ?? 0,
            ];
        }

        if ($lines === []) {
            return redirect()->back()->with('error', 'All items have already been fulfilled. Nothing to invoice.');
        }

        // The order's discount is stored as money. Invoice the share that
        // belongs to the lines still to deliver.
        $remaining = collect($lines)->sum(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price'] - $l['discount']);
        $orderSubtotal = (float) $salesOrder->subtotal;
        $discountShare = $orderSubtotal > 0 ? (float) ($salesOrder->discount_amount ?? 0) * min(1, $remaining / $orderSubtotal) : 0;

        // The same rules as every other invoice (R3): stock is checked and
        // reserved, which conversion used to skip.
        $invoice = DB::transaction(function () use ($save, $salesOrder, $lines, $discountShare) {
            $invoice = $save->create(auth()->user()->tenant_id, [
                'customer_id' => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'reference' => $salesOrder->order_number,
                'notes' => $salesOrder->notes,
                'terms' => $salesOrder->terms,
                'discount_type' => $discountShare > 0 ? 'fixed' : null,
                'discount_amount' => round($discountShare, 2),
                'items' => $lines,
            ], auth()->id());

            $salesOrder->update(['status' => 'invoiced']);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} created from Sales Order {$salesOrder->order_number}.");
    }

    public function createDeliveryNote(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, ['confirmed', 'processing'])) {
            return redirect()->back()->with('error', 'Only confirmed or processing orders can have delivery notes.');
        }

        return redirect()->route('delivery-notes.create', ['sales_order_id' => $salesOrder->id]);
    }
}
