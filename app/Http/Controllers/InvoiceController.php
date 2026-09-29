<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Invoice;
use App\Models\Item;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoices.index');
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        // Get items that either are services/don't track inventory OR have available stock
        $items = Item::where('is_active', true)
            ->with(['taxRate', 'taxGroup.taxRates', 'inventory'])
            ->where(function ($query) {
                $query->where('track_inventory', false)
                    ->orWhere('type', 'service')
                    ->orWhereHas('inventory', function ($q) {
                        $q->whereRaw('quantity - COALESCE(reserved_quantity, 0) > 0');
                    });
            })
            ->get();
        $invoiceNumber = Invoice::previewNumber(auth()->user()->tenant_id);

        return view('invoices.create', compact('customers', 'items', 'invoiceNumber'));
    }

    public function store(StoreInvoiceRequest $request, \App\Actions\Invoices\SaveInvoice $save)
    {
        // Same rules as the API, sales-order conversion and recurring
        // invoices (R3): stock check, totals, lines, reservation, journal.
        $invoice = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account', 'refunds']);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;

        // Load the active invoice template settings
        $template = $tenant->invoiceTemplate;
        if (! $template) {
            $template = \App\Models\InvoiceTemplate::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();
        }

        if ($template) {
            $templateSettings = array_merge(
                \App\Models\InvoiceTemplate::getDefaultSettings(),
                $template->settings ?? []
            );

            return view('invoices.templates.print-templated', compact('invoice', 'tenant', 'templateSettings'));
        }

        return view('invoices.print', compact('invoice', 'tenant'));
    }

    public function pdf(Invoice $invoice)
    {
        // Redirect to print view for now - can be enhanced with PDF generation later
        return $this->print($invoice);
    }

    public function edit(Invoice $invoice)
    {
        $customers = Customer::where('is_active', true)->get();
        $items = Item::where('is_active', true)
            ->with(['taxRate', 'taxGroup.taxRates'])
            ->get();
        $invoice->load('items');

        return view('invoices.edit', compact('invoice', 'customers', 'items'));
    }

    public function update(\App\Http\Requests\UpdateInvoiceRequest $request, Invoice $invoice, \App\Actions\Invoices\SaveInvoice $save)
    {
        if ($invoice->status === 'paid') {
            return redirect()->back()->with('error', 'Cannot edit a paid invoice.');
        }

        $save->update($invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice, \App\Actions\Invoices\DeleteInvoice $delete)
    {
        // Same rules as the API and the bulk delete (R3).
        if ($reason = $delete->blockedBecause($invoice)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($invoice);

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted successfully.');
    }

    public function send(Invoice $invoice, NotificationService $notificationService)
    {
        // Check if customer has email
        if (! $invoice->customer || ! $invoice->customer->email) {
            return redirect()->back()->with('error', 'Customer does not have an email address.');
        }

        // Send the invoice email
        $sent = $notificationService->sendInvoice($invoice);

        if ($sent) {
            $invoice->update(['status' => 'sent']);

            // Log the activity
            $invoice->logCustomActivity(ActivityLog::ACTION_SENT, "Invoice '{$invoice->invoice_number}' was sent to {$invoice->customer->email}");

            return redirect()->back()->with('success', "Invoice sent to {$invoice->customer->email}");
        }

        return redirect()->back()->with('error', 'Failed to send invoice. Please check the customer email address.');
    }

    public function release(Invoice $invoice)
    {
        // Check if invoice can be released
        if (! $invoice->canBeReleased()) {
            if ($invoice->isReleased()) {
                return redirect()->back()->with('error', 'Invoice has already been released.');
            }

            return redirect()->back()->with('error', 'Only paid invoices can be released.');
        }

        try {
            $waybillNumber = DB::transaction(function () use ($invoice) {
                // Generate waybill number
                $waybillNumber = Invoice::generateWaybillNumber(auth()->user()->tenant_id);

                // Deduct inventory for each item (move from reserved to sold)
                foreach ($invoice->items as $invoiceItem) {
                    if ($invoiceItem->item_id) {
                        $inventory = Inventory::where('item_id', $invoiceItem->item_id)
                            ->where('tenant_id', auth()->user()->tenant_id)
                            ->lockForUpdate()
                            ->first();

                        if ($inventory) {
                            // Deduct from both quantity and reserved_quantity
                            $previousQty = $inventory->quantity;
                            // Refuse rather than silently clamping stock at zero (M4)
                            if ((float) $inventory->quantity < (float) $invoiceItem->quantity) {
                                throw new \RuntimeException("Not enough stock to release {$invoiceItem->description}: {$inventory->quantity} on hand, {$invoiceItem->quantity} needed.");
                            }
                            $inventory->quantity = $inventory->quantity - $invoiceItem->quantity;
                            $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $invoiceItem->quantity);
                            $inventory->save();

                            // Record inventory history
                            InventoryHistory::create([
                                'tenant_id' => auth()->user()->tenant_id,
                                'item_id' => $invoiceItem->item_id,
                                'type' => 'out',
                                'quantity' => -$invoiceItem->quantity,
                                'reference_type' => 'invoice',
                                'reference_id' => $invoice->id,
                                'notes' => "Released via Invoice #{$invoice->invoice_number}, Waybill #{$waybillNumber}",
                                'created_by' => auth()->id(),
                            ]);
                        }
                    }
                }

                // Update invoice with release info
                $invoice->update([
                    'released_at' => now(),
                    'waybill_number' => $waybillNumber,
                ]);

                // Log the activity
                $invoice->logCustomActivity(ActivityLog::ACTION_RELEASED, "Invoice '{$invoice->invoice_number}' was released with Waybill #{$waybillNumber}");

                return $waybillNumber;
            });

            return redirect()->back()->with('success', "Invoice released successfully. Waybill Number: {$waybillNumber}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to release invoice: '.$e->getMessage());
        }
    }

    public function waybill(Invoice $invoice)
    {
        if (! $invoice->isReleased()) {
            return redirect()->back()->with('error', 'Invoice has not been released yet.');
        }

        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;

        return view('invoices.waybill', compact('invoice', 'tenant'));
    }
}
