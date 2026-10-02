<?php

namespace App\Http\Controllers;

use App\Actions\Quotations\ConvertQuotation;
use App\Actions\Quotations\DeleteQuotation;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\QuotationStatus;
use App\Http\Requests\SaveQuotationRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Quotation;
use App\Notifications\QuotationSentNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index()
    {
        return view('quotations.index');
    }

    public function create(Request $request)
    {
        $quotationNumber = Quotation::previewNumber(auth()->user()->tenant_id);
        // Customers and items are searched as you type (P9).
        $customer = $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null;
        $customerOptions = $this->customerOptions(collect([$customer])->filter());

        return view('quotations.create', compact('quotationNumber', 'customerOptions'));
    }

    public function store(SaveQuotationRequest $request, SaveQuotation $save)
    {
        $quotation = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('quotations.show', $quotation)->with('success', "Quotation {$quotation->quotation_number} created.");
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.item', 'createdBy', 'salesOrder', 'invoice']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        if (! in_array($quotation->status, QuotationStatus::editableValues(), true)) {
            return redirect()->route('quotations.show', $quotation)
                ->with('error', "A {$quotation->status} quotation can't be changed.");
        }

        $quotation->load(['customer', 'items.item']);
        $customerOptions = $this->customerOptions(collect([$quotation->customer])->filter());

        return view('quotations.edit', compact('quotation', 'customerOptions'));
    }

    public function update(SaveQuotationRequest $request, Quotation $quotation, SaveQuotation $save)
    {
        $save->update($quotation, $request->validated());

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function destroy(Quotation $quotation, DeleteQuotation $delete)
    {
        if ($reason = $delete->blockedBecause($quotation)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($quotation);

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    /**
     * Email the quotation to the customer with a PDF copy attached.
     */
    public function send(Request $request, Quotation $quotation)
    {
        $validated = $request->validate(['message' => 'nullable|string|max:2000']);

        if (! in_array($quotation->status, [QuotationStatus::Draft->value, QuotationStatus::Sent->value], true)) {
            return redirect()->back()->with('error', "A {$quotation->status} quotation can't be sent.");
        }
        if (! $quotation->customer?->email) {
            return redirect()->back()->with('error', 'This customer has no email address. Add one, or print the quotation and mark it as sent.');
        }

        try {
            $quotation->customer->notify(new QuotationSentNotification($quotation, $validated['message'] ?? null));
        } catch (\Throwable $e) {
            Log::error("Failed to send quotation {$quotation->quotation_number}: ".$e->getMessage());

            return redirect()->back()->with('error', 'The email could not be sent. Please try again.');
        }

        $this->markAsSent($quotation);
        $quotation->logCustomActivity(ActivityLog::ACTION_SENT, "Quotation '{$quotation->quotation_number}' was sent to {$quotation->customer->email}");

        return redirect()->back()->with('success', "Quotation emailed to {$quotation->customer->email}.");
    }

    /** For a quotation handed over on paper or sent another way. */
    public function markSent(Quotation $quotation)
    {
        if ($quotation->status !== QuotationStatus::Draft->value) {
            return redirect()->back()->with('error', 'Only a draft quotation can be marked as sent.');
        }

        $this->markAsSent($quotation);

        return redirect()->back()->with('success', 'Quotation marked as sent.');
    }

    public function accept(Quotation $quotation)
    {
        return $this->answer($quotation, QuotationStatus::Accepted, 'Quotation marked as accepted.');
    }

    public function reject(Quotation $quotation)
    {
        return $this->answer($quotation, QuotationStatus::Rejected, 'Quotation marked as rejected.');
    }

    public function convertToSalesOrder(Quotation $quotation, ConvertQuotation $convert)
    {
        try {
            $order = $convert->toSalesOrder($quotation, auth()->id());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Quotation converted to sales order {$order->order_number}.");
    }

    public function convertToInvoice(Quotation $quotation, ConvertQuotation $convert)
    {
        try {
            $invoice = $convert->toInvoice($quotation, auth()->id());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Quotation converted to draft invoice {$invoice->invoice_number}.");
    }

    public function print(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.item']);
        $tenant = auth()->user()->tenant;

        return view('quotations.print', compact('quotation', 'tenant'));
    }

    public function pdf(Quotation $quotation)
    {
        $quotation->load(['customer', 'items.item']);
        $tenant = auth()->user()->tenant;

        return Pdf::loadView('quotations.print', ['quotation' => $quotation, 'tenant' => $tenant, 'forPdf' => true])
            ->download("quotation-{$quotation->quotation_number}.pdf");
    }

    private function answer(Quotation $quotation, QuotationStatus $to, string $message)
    {
        if (! QuotationStatus::from($quotation->status)->canMoveTo($to) || $quotation->status === $to->value) {
            return redirect()->back()->with('error', "A {$quotation->status} quotation can't be marked {$to->value}.");
        }

        $quotation->update(['status' => $to->value]);

        return redirect()->back()->with('success', $message);
    }

    private function markAsSent(Quotation $quotation): void
    {
        $quotation->update([
            'status' => QuotationStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }

    /** @return array<int, array{id: string, name: string}> */
    private function customerOptions($customers): array
    {
        return $customers->map(fn ($c) => [
            'id' => (string) $c->id,
            'name' => $c->name.($c->company_name ? " ({$c->company_name})" : ''),
        ])->values()->all();
    }
}
