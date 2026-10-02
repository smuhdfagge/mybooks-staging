<?php

namespace App\Http\Controllers;

use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\SaveBill;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index()
    {
        return view('bills.index');
    }

    public function create(Request $request)
    {
        $billNumber = Bill::previewNumber(auth()->user()->tenant_id);

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
                // The order's line discount, for the share being billed.
                'discount' => (float) $line->quantity > 0 ? round((float) $line->discount * ($line->quantity_received > 0 ? $line->quantity_received : $line->quantity) / (float) $line->quantity, 2) : 0,
                'tax_rate' => (float) $line->tax_rate,
                'itemSearch' => $line->item?->name ?? '',
                'itemDropdownOpen' => false,
                'itemHighlightedIndex' => 0,
            ])->values()->all();
        }

        // Vendors and items are searched as you type (P9); only a vendor
        // already chosen is loaded here.
        $vendorId = old('vendor_id', $request->input('vendor_id', $purchaseOrder?->vendor_id));
        $vendorOptions = $this->vendorOptions($vendorId ? Vendor::whereKey($vendorId)->get() : collect());

        return view('bills.create', compact('vendorOptions', 'billNumber', 'purchaseOrder', 'prefillItems'));
    }

    public function store(StoreBillRequest $request, SaveBill $save)
    {
        // Same rules as the API and recurring bills (R3).
        $bill = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('bills.show', $bill)->with('success', 'Bill created.');
    }

    public function show(Bill $bill)
    {
        $bill->load(['vendor', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account', 'vendorCreditApplications.vendorCredit']);

        return view('bills.show', compact('bill'));
    }

    public function edit(Bill $bill)
    {
        if ($bill->status === 'paid') {
            return redirect()->route('bills.show', $bill)->with('error', 'Paid bills cannot be edited.');
        }

        // Only the bill's own vendor and items are loaded (P9).
        $bill->load(['vendor', 'items.item']);
        $vendorOptions = $this->vendorOptions(collect([$bill->vendor])->filter());

        return view('bills.edit', compact('bill', 'vendorOptions'));
    }

    public function update(UpdateBillRequest $request, Bill $bill, SaveBill $save)
    {
        if ($bill->status === 'paid') {
            return redirect()->route('bills.show', $bill)->with('error', 'Paid bills cannot be updated.');
        }

        $save->update($bill, $request->validated());

        return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
    }

    public function destroy(Bill $bill, DeleteBill $delete)
    {
        if ($reason = $delete->blockedBecause($bill)) {
            return redirect()->route('bills.index')->with('error', $reason);
        }
        $delete->handle($bill);

        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    /**
     * Vendors as options for the searchable vendor box.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function vendorOptions($vendors): array
    {
        return $vendors->map(fn ($v) => [
            'id' => (string) $v->id,
            'name' => $v->name.($v->company_name ? " ({$v->company_name})" : ''),
        ])->values()->all();
    }
}
