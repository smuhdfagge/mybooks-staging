<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryNoteController extends Controller
{
    public function index()
    {
        return view('delivery-notes.index');
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $customers = Customer::where('is_active', true)->get();
        $deliveryNumber = DeliveryNote::generateNumber($tenantId);

        $salesOrder = null;
        if ($request->has('sales_order_id')) {
            $salesOrder = SalesOrder::with('items.item')
                ->findOrFail($request->sales_order_id);
        }

        return view('delivery-notes.create', compact('customers', 'deliveryNumber', 'salesOrder'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'sales_order_id' => ['nullable', Rule::exists('sales_orders', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'delivery_date' => 'required|date',
            'shipping_method' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'items.*.description' => 'required|string',
            'items.*.quantity_ordered' => 'required|numeric|min:0',
            'items.*.quantity_delivered' => 'required|numeric|min:0.01',
        ]);

        $deliveryNote = DB::transaction(function () use ($tenantId, $validated) {
            $dn = DeliveryNote::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'],
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'delivery_number' => DeliveryNote::generateNumber($tenantId),
                'delivery_date' => $validated['delivery_date'],
                'status' => 'draft',
                'shipping_method' => $validated['shipping_method'] ?? null,
                'tracking_number' => $validated['tracking_number'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $dn->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity_ordered' => $itemData['quantity_ordered'],
                    'quantity_delivered' => $itemData['quantity_delivered'],
                ]);
            }

            return $dn;
        });

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Delivery note created.');
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer', 'salesOrder', 'invoice', 'items.item', 'createdBy']);

        return view('delivery-notes.show', compact('deliveryNote'));
    }

    public function dispatch(DeliveryNote $deliveryNote)
    {
        if (! $deliveryNote->dispatch()) {
            return redirect()->back()->with('error', 'Only draft delivery notes can be dispatched.');
        }

        return redirect()->back()->with('success', 'Delivery note dispatched.');
    }

    public function confirmDelivery(Request $request, DeliveryNote $deliveryNote)
    {
        $validated = $request->validate([
            'received_by' => 'required|string|max:255',
        ]);

        $result = DB::transaction(function () use ($deliveryNote, $validated) {
            return $deliveryNote->confirmDelivery($validated['received_by']);
        });

        if (! $result) {
            return redirect()->back()->with('error', 'This delivery note cannot be confirmed.');
        }

        return redirect()->back()->with('success', 'Delivery confirmed. Inventory updated and fulfillment tracked.');
    }

    public function destroy(DeliveryNote $deliveryNote)
    {
        if ($deliveryNote->status === DeliveryNote::STATUS_DELIVERED) {
            return redirect()->back()->with('error', 'Cannot delete a delivered note.');
        }

        DB::transaction(function () use ($deliveryNote) {
            $deliveryNote->items()->delete();
            $deliveryNote->delete();
        });

        return redirect()->route('delivery-notes.index')->with('success', 'Delivery note deleted.');
    }

    public function print(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer', 'items.item', 'salesOrder']);
        $tenant = auth()->user()->tenant;

        return view('delivery-notes.print', compact('deliveryNote', 'tenant'));
    }
}
