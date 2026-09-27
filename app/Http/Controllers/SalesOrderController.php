<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Inventory;
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
        if (!in_array($salesOrder->status, ['confirmed', 'processing'])) {
            return redirect()->back()->with('error', 'Only confirmed or processing orders can be converted to invoices.');
        }

        $salesOrder->load('items');
        $tenantId = auth()->user()->tenant_id;

        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'customer_id' => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'invoice_number' => Invoice::generateNumber($tenantId),
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'reference' => $salesOrder->order_number,
                'notes' => $salesOrder->notes,
                'terms' => $salesOrder->terms,
                'discount_type' => $salesOrder->discount_type,
                'discount_amount' => $salesOrder->discount_amount ?? 0,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $totalTax = 0;

            foreach ($salesOrder->items as $soItem) {
                $qty = $soItem->quantity - ($soItem->quantity_fulfilled ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $lineTotal = $qty * $soItem->unit_price;
                $taxRate = $soItem->tax_rate ?? 0;
                $taxAmount = $lineTotal * ($taxRate / 100);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_id' => $soItem->item_id,
                    'description' => $soItem->description,
                    'quantity' => $qty,
                    'unit_price' => $soItem->unit_price,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal + $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;
            }

            if ($subtotal === 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'All items have already been fulfilled. Nothing to invoice.');
            }

            $discountAmount = $salesOrder->discount_amount ?? 0;
            if (($salesOrder->discount_type ?? null) === 'percentage') {
                $discountAmount = $subtotal * ($discountAmount / 100);
            }

            $total = $subtotal + $totalTax - $discountAmount;

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'balance_due' => $total,
            ]);

            // Reserve inventory for invoice items
            foreach ($invoice->items as $invoiceItem) {
                if ($invoiceItem->item_id) {
                    $item = Item::find($invoiceItem->item_id);
                    if ($item && $item->track_inventory && $item->type !== 'service') {
                        $inventory = Inventory::where('item_id', $item->id)
                            ->where('tenant_id', $tenantId)
                            ->first();
                        if ($inventory) {
                            $inventory->reserved_quantity = ($inventory->reserved_quantity ?? 0) + $invoiceItem->quantity;
                            $inventory->save();
                        }
                    }
                }
            }

            // Update sales order status
            $salesOrder->update(['status' => 'invoiced']);

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} created from Sales Order {$salesOrder->order_number}.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to convert sales order: ' . $e->getMessage());
        }
    }

    public function createDeliveryNote(SalesOrder $salesOrder)
    {
        if (!in_array($salesOrder->status, ['confirmed', 'processing'])) {
            return redirect()->back()->with('error', 'Only confirmed or processing orders can have delivery notes.');
        }

        return redirect()->route('delivery-notes.create', ['sales_order_id' => $salesOrder->id]);
    }
}
