<?php

namespace App\Http\Controllers;

use App\Actions\DeliveryNotes\SaveDeliveryNote;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\SalesOrders\DeleteSalesOrder;
use App\Actions\SalesOrders\SaveSalesOrder;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
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

    public function store(StoreSalesOrderRequest $request, SaveSalesOrder $save)
    {
        // Same rules as the API (R3).
        $order = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('sales-orders.show', $order)->with('success', 'Sales order created.');
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.item', 'invoices', 'deliveryNotes', 'quotation']);

        return view('sales-orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, SaveSalesOrder::EDITABLE, true)) {
            return redirect()->route('sales-orders.show', $salesOrder)->with('error', "A {$salesOrder->status} sales order can't be changed.");
        }

        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $salesOrder->load('items');

        return view('sales-orders.edit', compact('salesOrder', 'customers', 'items'));
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder, SaveSalesOrder $save)
    {
        // This used to save nothing at all (R3).
        $save->update($salesOrder, $request->validated());

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function destroy(SalesOrder $salesOrder, DeleteSalesOrder $delete)
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

    public function convertToInvoice(SalesOrder $salesOrder, SaveInvoice $save)
    {
        if (! in_array($salesOrder->status, ['confirmed', 'processing', 'invoiced', 'completed'], true)) {
            return redirect()->back()->with('error', 'Only confirmed orders can be converted to invoices.');
        }

        $salesOrder->load('items');

        // Lines not yet invoiced. Delivered goods are invoiced too: this used
        // to invoice "ordered minus delivered", so goods delivered first
        // (delivery notes) were never billed.
        $lines = [];
        $invoicedNow = [];
        foreach ($salesOrder->items as $soItem) {
            $qty = round((float) $soItem->quantity - (float) $soItem->quantity_invoiced, 2);
            if ($qty <= 0) {
                continue;
            }
            $invoicedNow[$soItem->id] = $qty;
            $lines[] = [
                'item_id' => $soItem->item_id,
                'description' => $soItem->description,
                'quantity' => $qty,
                'unit_price' => $soItem->unit_price,
                // The line discount is money for the whole line; invoice the part not yet invoiced.
                'discount' => (float) $soItem->quantity > 0 ? round((float) $soItem->discount * $qty / (float) $soItem->quantity, 2) : 0,
                'discount_type' => 'fixed',
                'tax_rate' => $soItem->tax_rate ?? 0,
            ];
        }

        if ($lines === []) {
            return redirect()->back()->with('error', 'Everything on this order has already been invoiced.');
        }

        // The order's discount is stored as money. Invoice the share that
        // belongs to the lines not yet invoiced.
        $remaining = collect($lines)->sum(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price'] - $l['discount']);
        $orderSubtotal = (float) $salesOrder->subtotal;
        $discountShare = $orderSubtotal > 0 ? (float) ($salesOrder->discount_amount ?? 0) * min(1, $remaining / $orderSubtotal) : 0;

        // The same rules as every other invoice (R3): stock is checked and
        // reserved, which conversion used to skip.
        $invoice = DB::transaction(function () use ($save, $salesOrder, $lines, $discountShare, $invoicedNow) {
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

            foreach ($salesOrder->items as $soItem) {
                if (isset($invoicedNow[$soItem->id])) {
                    $soItem->update(['quantity_invoiced' => round((float) $soItem->quantity_invoiced + $invoicedNow[$soItem->id], 2)]);
                }
            }

            // A fully delivered order stays completed; otherwise it is invoiced.
            if ($salesOrder->status !== 'completed') {
                $salesOrder->update(['status' => 'invoiced']);
            }

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} created from Sales Order {$salesOrder->order_number}.");
    }

    public function createDeliveryNote(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, SaveDeliveryNote::OPEN_ORDER_STATUSES, true)) {
            return redirect()->back()->with('error', 'Only confirmed orders with goods still to deliver can have delivery notes.');
        }

        return redirect()->route('delivery-notes.create', ['sales_order_id' => $salesOrder->id]);
    }
}
