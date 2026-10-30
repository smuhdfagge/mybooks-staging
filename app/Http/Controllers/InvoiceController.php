<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\SaveInvoice;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Models\Warehouse;
use App\Services\NotificationService;
use App\Support\InvoiceDocument;
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

        Warehouse::rememberChoice($request->input('warehouse_id'));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item', 'payments.createdBy', 'createdBy', 'journal.entries.account', 'refunds',
            'creditNotes' => fn ($q) => $q->latest('credit_note_date')->latest('id'), 'creditNoteApplications.creditNote']);

        // The invoice page shows the document itself, exactly as it prints
        // (invoice rebuild).
        $document = InvoiceDocument::data($invoice);

        return view('invoices.show', compact('invoice', 'document'));
    }

    /** The document on its own page, in the same tab (old links and emails). */
    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.item']);

        return view('invoices.print', InvoiceDocument::data($invoice));
    }

    /** A real A4 PDF of the same document, to save or send. */
    public function pdf(Invoice $invoice)
    {
        return response(InvoiceDocument::pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.addslashes(InvoiceDocument::fileName($invoice)).'"',
        ]);
    }

    public function edit(Invoice $invoice)
    {
        // Accepted by NRS: no editing, a credit note is how it is corrected (session 18).
        if (EInvoiceSubmission::isLocked($invoice)) {
            return redirect()->route('invoices.show', $invoice)->with('error', EInvoiceSubmission::lockedMessage($invoice));
        }

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

        // Marked sent (which posts its journal) and emailed together: a
        // posting that is refused (stock with no cost, a closed month) sends
        // nothing, and an email that fails leaves it as it was.
        try {
            DB::transaction(function () use ($invoice, $notificationService) {
                $invoice->update(['status' => 'sent']);
                if (! $notificationService->sendInvoice($invoice)) {
                    throw new BusinessRuleException('Failed to send invoice. Please check the customer email address.');
                }
            });
        } catch (BusinessRuleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Log the activity
        $invoice->logCustomActivity(ActivityLog::ACTION_SENT, "Invoice '{$invoice->invoice_number}' was sent to {$invoice->customer->email}");

        return redirect()->back()->with('success', "Invoice sent to {$invoice->customer->email}");
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
                // Out of the invoice's warehouse (session 12).
                $waybillNumber = $invoice->releaseStock();

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
