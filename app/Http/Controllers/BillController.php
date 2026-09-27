<?php

namespace App\Http\Controllers;

use App\Models\Bill;
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

    public function create()
    {
        $vendors = Vendor::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $billNumber = Bill::generateNumber(auth()->user()->tenant_id);
        return view('bills.create', compact('vendors', 'items', 'billNumber'));
    }

    public function store(StoreBillRequest $request)
    {
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

            $bill = Bill::create([
                'tenant_id' => $tenantId,
                'vendor_id' => $validated['vendor_id'],
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
