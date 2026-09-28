<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\BillItem;
use App\Models\Vendor;
use App\Models\Item;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    public function index()
    {
        return view('bills.index');
    }

    public function create(Request $request)
    {
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $billNumber = Bill::generateNumber(auth()->user()->tenant_id);

        // "Convert to bill" from a purchase order fills the form from the
        // order (finding N8).
        $purchaseOrder = null;
        $prefillItems = null;
        if ($request->filled('purchase_order_id')) {
            $purchaseOrder = PurchaseOrder::with('items.item')->findOrFail($request->integer('purchase_order_id'));

            if (! in_array($purchaseOrder->status, PurchaseOrder::BILLABLE, true)) {
                return redirect()->route('purchase-orders.show', $purchaseOrder)
                    ->with('error', 'This purchase order can no longer be billed.');
            }

            $prefillItems = $purchaseOrder->items->map(fn ($line) => [
                'item_id' => (string) $line->item_id,
                'description' => $line->description ?: $line->item?->name,
                // Bill what arrived when goods have been received, else what was ordered
                'quantity' => (float) ($line->quantity_received > 0 ? $line->quantity_received : $line->quantity),
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'itemSearch' => $line->item?->name ?? '',
                'itemDropdownOpen' => false,
                'itemHighlightedIndex' => 0,
            ])->values()->all();
        }

        return view('bills.create', compact('vendors', 'items', 'billNumber', 'purchaseOrder', 'prefillItems'));
    }

    public function store(StoreBillRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $purchaseOrder = null;
        if (! empty($validated['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::find($validated['purchase_order_id']);

            if ((int) $purchaseOrder->vendor_id !== (int) $validated['vendor_id']) {
                return back()->withInput()->withErrors(['vendor_id' => "The vendor must be the purchase order's vendor."]);
            }
        }

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $itemTotal -= $discount;
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
                
                $subtotal += $item['quantity'] * $item['unit_price'];
                $totalDiscount += $discount;
                $totalTax += $tax;
            }

            $bill = Bill::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $validated['vendor_id'],
                'purchase_order_id' => $purchaseOrder?->id,
                'bill_number' => Bill::generateNumber($tenantId),
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $totalDiscount,
                'total' => $subtotal - $totalDiscount + $totalTax,
                'balance_due' => $subtotal - $totalDiscount + $totalTax,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'unpaid',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $tax = ($itemTotal - $discount) * (($item['tax_rate'] ?? 0) / 100);

                BillItem::create([
                    'bill_id' => $bill->id,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $tax,
                    'discount' => $discount,
                    'total' => $itemTotal - $discount + $tax,
                ]);
            }

            if ($purchaseOrder) {
                // Locked so two people can't bill the same order at once
                $purchaseOrder = PurchaseOrder::lockForUpdate()->find($purchaseOrder->id);
                if (! in_array($purchaseOrder->status, PurchaseOrder::BILLABLE, true)) {
                    DB::rollBack();

                    return back()->withInput()->withErrors(['purchase_order_id' => 'This purchase order has already been billed or cannot be billed.']);
                }
                $purchaseOrder->update(['status' => PurchaseOrder::STATUS_BILLED]);
            }

            DB::commit();

            return redirect()->route('bills.show', $bill)->with('success', 'Bill created.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create bill.']);
        }
    }

    public function show(Bill $bill)
    {
        $bill->load(['vendor', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account']);
        return view('bills.show', compact('bill'));
    }

    public function edit(Bill $bill)
    {
        if ($bill->status === 'paid') {
            return redirect()->route('bills.show', $bill)->with('error', 'Paid bills cannot be edited.');
        }

        $bill->load('items');
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        return view('bills.edit', compact('bill', 'vendors', 'items'));
    }

    public function update(UpdateBillRequest $request, Bill $bill)
    {
        if ($bill->status === 'paid') {
            return redirect()->route('bills.show', $bill)->with('error', 'Paid bills cannot be updated.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $itemTotal -= $discount;
                $tax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
                
                $subtotal += $item['quantity'] * $item['unit_price'];
                $totalDiscount += $discount;
                $totalTax += $tax;
            }

            $totalAmount = $subtotal - $totalDiscount + $totalTax;
            $amountPaid = $bill->total - $bill->balance_due;
            
            $bill->update([
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'discount_amount' => $totalDiscount,
                'total' => $totalAmount,
                'balance_due' => max(0, $totalAmount - $amountPaid),
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $bill->items()->delete();

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $tax = ($itemTotal - $discount) * (($item['tax_rate'] ?? 0) / 100);

                BillItem::create([
                    'bill_id' => $bill->id,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tax_amount' => $tax,
                    'discount' => $discount,
                    'total' => $itemTotal - $discount + $tax,
                ]);
            }

            DB::commit();

            return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to update bill.']);
        }
    }

    public function destroy(Bill $bill)
    {
        if ($bill->payments()->exists()) {
            return redirect()->route('bills.index')->with('error', 'Cannot delete bill with payments.');
        }

        $bill->items()->delete();
        $bill->delete();
        
        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }
}
