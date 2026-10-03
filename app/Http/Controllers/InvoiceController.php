<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\SaveInvoice;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
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
        // Customers and items are searched as you type (P9); only a
        // customer already chosen is loaded here.
        $customerId = old('customer_id', request('customer_id'));
        $customerOptions = $this->customerOptions($customerId ? Customer::whereKey($customerId)->get() : collect());
        $invoiceNumber = Invoice::previewNumber(auth()->user()->tenant_id);

        return view('invoices.create', compact('customerOptions', 'invoiceNumber'));
    }

    public function store(StoreInvoiceRequest $request, SaveInvoice $save)
    {
        // Same rules as the API, sales-order conversion and recurring
        // invoices (R3): stock check, totals, lines, reservation, journal.
        $invoice = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account', 'refunds',
            'creditNotes' => fn ($q) => $q->latest('credit_note_date')->latest('id'), 'creditNoteApplications.creditNote']);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'tenant']);
        $tenant = $invoice->tenant ?? auth()->user()->tenant;

        // Load the active invoice template settings
        $template = $tenant->invoiceTemplate;
        if (! $template) {
            $template = InvoiceTemplate::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();
        }

        if ($template) {
            $templateSettings = array_merge(
                InvoiceTemplate::getDefaultSettings(),
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
        // Only the invoice's own customer and items are loaded (P9).
        $invoice->load(['customer', 'items.item']);
        $customerOptions = $this->customerOptions(collect([$invoice->customer])->filter());

        return view('invoices.edit', compact('invoice', 'customerOptions'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, SaveInvoice $save)
    {
        if ($invoice->status === 'paid') {
            return redirect()->back()->with('error', 'Cannot edit a paid invoice.');
        }

        $save->update($invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice, DeleteInvoice $delete)
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

    /**
     * Customers as options for the searchable customer box.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function customerOptions($customers): array
    {
        return $customers->map(fn ($c) => [
            'id' => (string) $c->id,
            'name' => $c->name.($c->company_name ? " ({$c->company_name})" : ''),
        ])->values()->all();
    }
}
