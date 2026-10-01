<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Sales\DocumentTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index()
    {
        return view('quotations.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->with(['taxRate', 'taxGroup.taxRates'])->get();
        $quotationNumber = Quotation::previewNumber(auth()->user()->tenant_id);

        return view('quotations.create', compact('customers', 'items', 'quotationNumber'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'quotation_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:quotation_date',
            'reference' => 'nullable|string|max:100',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $quotation = DB::transaction(function () use ($tenantId, $validated) {
            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'],
                'quotation_number' => Quotation::generateNumber($tenantId),
                'quotation_date' => $validated['quotation_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'discount_type' => $validated['discount_type'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->writeLines($quotation, $validated);

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created.');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.item', 'createdBy', 'salesOrder']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        if (in_array($quotation->status, ['accepted', 'converted'])) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', 'Cannot edit an accepted or converted quotation.');
        }

        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->with(['taxRate', 'taxGroup.taxRates'])->get();
        $quotation->load('items');

        return view('quotations.edit', compact('quotation', 'customers', 'items'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        if (in_array($quotation->status, ['accepted', 'converted'])) {
            return redirect()->back()->with('error', 'Cannot edit an accepted or converted quotation.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'quotation_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:quotation_date',
            'reference' => 'nullable|string|max:100',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percentage',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update([
                'customer_id' => $validated['customer_id'],
                'quotation_date' => $validated['quotation_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'discount_type' => $validated['discount_type'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
            ]);

            $quotation->items()->delete();

            $this->writeLines($quotation, $validated);
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function destroy(Quotation $quotation)
    {
        if (in_array($quotation->status, ['accepted', 'converted'])) {
            return redirect()->back()->with('error', 'Cannot delete an accepted or converted quotation.');
        }

        DB::transaction(function () use ($quotation) {
            $quotation->items()->delete();
            $quotation->delete();
        });

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    public function send(Quotation $quotation)
    {
        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
        }

        return redirect()->back()->with('success', 'Quotation marked as sent.');
    }

    public function accept(Quotation $quotation)
    {
        if (! in_array($quotation->status, ['draft', 'sent'])) {
            return redirect()->back()->with('error', 'Only draft or sent quotations can be accepted.');
        }

        $quotation->update(['status' => 'accepted']);

        return redirect()->back()->with('success', 'Quotation accepted.');
    }

    public function reject(Quotation $quotation)
    {
        if (! in_array($quotation->status, ['draft', 'sent'])) {
            return redirect()->back()->with('error', 'Only draft or sent quotations can be rejected.');
        }

        $quotation->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Quotation rejected.');
    }

    public function convertToSalesOrder(Quotation $quotation)
    {
        if (! in_array($quotation->status, ['accepted', 'draft', 'sent'])) {
            return redirect()->back()->with('error', 'This quotation cannot be converted.');
        }

        $salesOrder = DB::transaction(function () use ($quotation) {
            return $quotation->convertToSalesOrder();
        });

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', "Quotation converted to Sales Order {$salesOrder->order_number}.");
    }

    public function print(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.item']);
        $tenant = auth()->user()->tenant;

        return view('quotations.print', compact('quotation', 'tenant'));
    }

    /**
     * Lines and totals by the same rules as sales orders and invoices
     * (A4, R3): VAT after discounts. It was charged before the discount,
     * so a converted quotation came out at a different total.
     *
     * @param  array<string, mixed>  $validated
     */
    private function writeLines(Quotation $quotation, array $validated): void
    {
        $totals = DocumentTotals::calculate($validated['items'], $validated['discount_type'] ?? null, $validated['discount_amount'] ?? 0);

        foreach ($totals['lines'] as $line) {
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'] ?? 0,
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'total' => $line['total'],
            ]);
        }

        $quotation->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total' => $totals['total'],
        ]);
    }
}
