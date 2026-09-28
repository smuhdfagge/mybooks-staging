<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesReceipt;
use App\Models\SalesReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesReceiptController extends Controller
{
    public function index()
    {
        return view('sales-receipts.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();
        $receiptNumber = SalesReceipt::generateNumber(auth()->user()->tenant_id);

        return view('sales-receipts.create', compact('customers', 'items', 'receiptNumber'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'receipt_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $receipt = DB::transaction(function () use ($tenantId, $validated) {
            $this->assertStockAvailable($validated['items']);

            $receipt = SalesReceipt::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'] ?? null,
                'receipt_number' => SalesReceipt::generateNumber($tenantId),
                'receipt_date' => $validated['receipt_date'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;

            foreach ($validated['items'] as $itemData) {
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];

                SalesReceiptItem::create([
                    'sales_receipt_id' => $receipt->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $receipt->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return $receipt;
        });

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
        $items = Item::where('is_active', true)->get();

        return view('sales-receipts.edit', compact('salesReceipt', 'customers', 'items'));
    }

    public function update(Request $request, SalesReceipt $salesReceipt)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'receipt_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($salesReceipt, $validated) {
            $this->assertStockAvailable($validated['items'], $salesReceipt);

            $salesReceipt->update([
                'customer_id' => $validated['customer_id'] ?? null,
                'receipt_date' => $validated['receipt_date'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete existing items and recreate
            $salesReceipt->items()->delete();

            $subtotal = 0;

            foreach ($validated['items'] as $itemData) {
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];

                SalesReceiptItem::create([
                    'sales_receipt_id' => $salesReceipt->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $salesReceipt->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);
        });

        return redirect()->route('sales-receipts.show', $salesReceipt)->with('success', 'Sales receipt updated successfully.');
    }

    /**
     * A cash sale hands the goods over at once, so there must be enough
     * unreserved stock. Stock this receipt already took (when editing) counts
     * as available again. Rows are locked until the receipt is saved.
     */
    private function assertStockAvailable(array $lines, ?SalesReceipt $receipt = null): void
    {
        $tenantId = auth()->user()->tenant_id;
        $needed = [];
        foreach ($lines as $index => $line) {
            if (! empty($line['item_id'])) {
                $needed[$line['item_id']][] = [$index, (float) $line['quantity']];
            }
        }

        $errors = [];
        foreach ($needed as $itemId => $uses) {
            $item = Item::find($itemId);
            if (! $item || ! $item->track_inventory || $item->type === 'service') {
                continue;
            }

            $inventory = \App\Models\Inventory::where('tenant_id', $tenantId)->where('item_id', $itemId)->lockForUpdate()->first();
            $available = $inventory ? (float) $inventory->available_quantity : 0.0;
            if ($receipt) {
                $available += (float) \App\Models\InventoryLayerConsumption::where('source_type', SalesReceipt::class)
                    ->where('source_id', $receipt->id)->where('item_id', $itemId)->where('reduced_on_hand', true)->sum('quantity');
            }

            $total = array_sum(array_column($uses, 1));
            if ($total - $available > 0.00001) {
                $errors["items.{$uses[0][0]}.quantity"] = "Not enough stock for '{$item->name}'. Available: ".rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.').", requested: {$total}.";
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
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
