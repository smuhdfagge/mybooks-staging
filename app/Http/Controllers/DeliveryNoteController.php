<?php

namespace App\Http\Controllers;

use App\Actions\DeliveryNotes\DeleteDeliveryNote;
use App\Actions\DeliveryNotes\SaveDeliveryNote;
use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryNoteController extends Controller
{
    public function index()
    {
        return view('delivery-notes.index');
    }

    /**
     * A delivery note is made from a sales order. Without one, list the
     * orders that still have goods to deliver.
     */
    public function create(Request $request, SaveDeliveryNote $save)
    {
        $tenantId = auth()->user()->tenant_id;
        $deliveryNumber = DeliveryNote::previewNumber($tenantId);

        if (! $request->filled('sales_order_id')) {
            $orders = SalesOrder::with('customer')
                ->whereIn('status', SaveDeliveryNote::OPEN_ORDER_STATUSES)
                ->whereHas('items', fn ($q) => $q->whereColumn('quantity_fulfilled', '<', 'quantity'))
                ->latest('order_date')->limit(100)->get();

            return view('delivery-notes.choose-order', compact('orders'));
        }

        $salesOrder = SalesOrder::with(['items.item', 'customer'])->findOrFail($request->integer('sales_order_id'));
        if (! in_array($salesOrder->status, SaveDeliveryNote::OPEN_ORDER_STATUSES, true)) {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', "Sales order {$salesOrder->order_number} is {$salesOrder->status}, so it can't be delivered.");
        }
        $outstanding = $save->outstanding($salesOrder);

        return view('delivery-notes.create', compact('deliveryNumber', 'salesOrder', 'outstanding'));
    }

    public function store(Request $request, SaveDeliveryNote $save)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'sales_order_id' => ['required', Rule::exists('sales_orders', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'delivery_date' => 'required|date',
            'shipping_method' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.sales_order_item_id' => 'required|integer',
            'lines.*.quantity' => 'nullable|numeric|min:0',
        ]);

        $order = SalesOrder::findOrFail($validated['sales_order_id']);
        $note = $save->create($order, $validated, auth()->id());

        return redirect()->route('delivery-notes.show', $note)->with('success', "Delivery note {$note->delivery_number} created.");
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer', 'salesOrder.invoices', 'items.item', 'createdBy']);

        return view('delivery-notes.show', compact('deliveryNote'));
    }

    public function dispatch(DeliveryNote $deliveryNote)
    {
        if ($deliveryNote->status !== DeliveryNoteStatus::Draft->value) {
            return redirect()->back()->with('error', 'Only a draft delivery note can be dispatched.');
        }

        $deliveryNote->dispatch();

        return redirect()->back()->with('success', 'Delivery note dispatched. The sales order now shows these goods as delivered.');
    }

    public function confirmDelivery(Request $request, DeliveryNote $deliveryNote)
    {
        $validated = $request->validate(['received_by' => 'required|string|max:255']);

        if (! in_array($deliveryNote->status, [DeliveryNoteStatus::Dispatched->value, DeliveryNoteStatus::InTransit->value], true)) {
            return redirect()->back()->with('error', 'Dispatch the delivery note before confirming delivery.');
        }

        $deliveryNote->markDelivered($validated['received_by']);

        return redirect()->back()->with('success', "Delivery confirmed: received by {$validated['received_by']}.");
    }

    public function cancel(DeliveryNote $deliveryNote)
    {
        if (! DeliveryNoteStatus::from($deliveryNote->status)->canMoveTo(DeliveryNoteStatus::Cancelled)
            || $deliveryNote->status === DeliveryNoteStatus::Cancelled->value) {
            return redirect()->back()->with('error', "A {$deliveryNote->status} delivery note can't be cancelled.");
        }

        $deliveryNote->cancel();

        return redirect()->back()->with('success', 'Delivery note cancelled.');
    }

    public function destroy(DeliveryNote $deliveryNote, DeleteDeliveryNote $delete)
    {
        if ($reason = $delete->blockedBecause($deliveryNote)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($deliveryNote);

        return redirect()->route('delivery-notes.index')->with('success', 'Delivery note deleted.');
    }

    public function print(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer', 'items.item', 'salesOrder']);
        $tenant = auth()->user()->tenant;

        return view('delivery-notes.print', compact('deliveryNote', 'tenant'));
    }

    public function pdf(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer', 'items.item', 'salesOrder']);
        $tenant = auth()->user()->tenant;

        return Pdf::loadView('delivery-notes.print', ['deliveryNote' => $deliveryNote, 'tenant' => $tenant, 'forPdf' => true])
            ->download("delivery-note-{$deliveryNote->delivery_number}.pdf");
    }
}
