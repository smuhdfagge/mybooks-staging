<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesReceipt;
use Illuminate\Support\Facades\DB;

class SalesReceiptController extends Controller
{
    public function index()
    {
        return view('sales-receipts.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->with(['taxRate', 'taxGroup.taxRates'])->get();
        $receiptNumber = SalesReceipt::previewNumber(auth()->user()->tenant_id);

        return view('sales-receipts.create', compact('customers', 'items', 'receiptNumber'));
    }

    public function store(\App\Http\Requests\StoreSalesReceiptRequest $request, \App\Actions\SalesReceipts\SaveSalesReceipt $save)
    {
        // VAT and discounts by the same rules as invoices (R3).
        $receipt = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('sales-receipts.show', $receipt)->with('success', 'Sales receipt created.');
    }

    public function show(SalesReceipt $salesReceipt)
    {
        $salesReceipt->load(['customer', 'items.item']);

        return view('sales-receipts.show', compact('salesReceipt'));
    }

    /**
     * Download the receipt as a PDF (the route existed without a method, N9).
     */
    public function pdf(SalesReceipt $salesReceipt)
    {
        $salesReceipt->load(['customer', 'items.item', 'tenant']);
        $tenant = $salesReceipt->tenant ?? auth()->user()->tenant;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('sales-receipts.print', compact('salesReceipt', 'tenant'))
            ->download("sales-receipt-{$salesReceipt->receipt_number}.pdf");
    }

    public function edit(SalesReceipt $salesReceipt)
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->with(['taxRate', 'taxGroup.taxRates'])->get();

        return view('sales-receipts.edit', compact('salesReceipt', 'customers', 'items'));
    }

    public function update(\App\Http\Requests\StoreSalesReceiptRequest $request, SalesReceipt $salesReceipt, \App\Actions\SalesReceipts\SaveSalesReceipt $save)
    {
        $save->update($salesReceipt, $request->validated());

        return redirect()->route('sales-receipts.show', $salesReceipt)->with('success', 'Sales receipt updated successfully.');
    }

    public function destroy(SalesReceipt $salesReceipt)
    {
        DB::transaction(function () use ($salesReceipt) {
            $salesReceipt->items()->delete();
            $salesReceipt->delete();
        });

        return redirect()->route('sales-receipts.index')->with('success', 'Deleted.');
    }
}
