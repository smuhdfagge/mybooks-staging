<?php

namespace App\Http\Controllers;

use App\Actions\CreditNotes\DeleteCreditNote;
use App\Actions\CreditNotes\OpenCreditNote;
use App\Actions\CreditNotes\RefundCreditNote;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\CreditNotes\VoidCreditNote;
use App\Enums\CreditNoteStatus;
use App\Http\Requests\SaveCreditNoteRequest;
use App\Models\Bank;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditNoteController extends Controller
{
    public function index()
    {
        return view('credit-notes.index');
    }

    /**
     * New credit note. From an invoice (?invoice_id=) the customer, invoice
     * and lines are filled in, at the price actually charged (after
     * discounts), ready to cut down to what is being credited.
     */
    public function create(Request $request)
    {
        $creditNoteNumber = CreditNote::previewNumber(auth()->user()->tenant_id);
        $reasons = CreditNote::REASONS;

        $invoice = null;
        $lines = [];
        $customer = null;
        if ($request->filled('invoice_id')) {
            $invoice = Invoice::with(['items.item', 'customer'])->findOrFail($request->integer('invoice_id'));
            $customer = $invoice->customer;
            $lines = $invoice->items->map(fn ($l) => [
                'item_id' => $l->item_id,
                'item_name' => $l->item?->name,
                'description' => $l->description,
                'quantity' => (float) $l->quantity,
                // Net price per unit: the line less its VAT, so any discount is already in it.
                'unit_price' => (float) $l->quantity > 0 ? round(((float) $l->total - (float) $l->tax_amount) / (float) $l->quantity, 2) : 0,
                'tax_rate' => (float) $l->tax_rate,
            ])->all();
        } elseif ($request->filled('customer_id')) {
            $customer = Customer::find($request->integer('customer_id'));
        }
        $customerOptions = $this->customerOptions(collect([$customer])->filter());

        return view('credit-notes.create', compact('creditNoteNumber', 'reasons', 'invoice', 'lines', 'customerOptions'));
    }

    public function store(SaveCreditNoteRequest $request, SaveCreditNote $save)
    {
        $note = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('credit-notes.show', $note)->with('success', "Credit note {$note->credit_note_number} created as a draft. Open it to post it.");
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'invoice', 'items.item', 'applications.invoice', 'refunds.bank', 'createdBy']);

        $invoices = $creditNote->status === CreditNoteStatus::Open->value
            ? Invoice::where('customer_id', $creditNote->customer_id)
                ->where('balance_due', '>', 0)
                ->whereIn('status', ['unpaid', 'partial', 'sent', 'overdue'])
                ->orderBy('due_date')->get()
            : collect();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('credit-notes.show', compact('creditNote', 'invoices', 'banks'));
    }

    public function edit(CreditNote $creditNote)
    {
        if ($creditNote->status !== CreditNoteStatus::Draft->value) {
            return redirect()->route('credit-notes.show', $creditNote)->with('error', 'Only a draft credit note can be changed.');
        }

        $creditNote->load(['customer', 'invoice', 'items.item']);
        $reasons = CreditNote::REASONS;
        $customerOptions = $this->customerOptions(collect([$creditNote->customer])->filter());

        return view('credit-notes.edit', compact('creditNote', 'reasons', 'customerOptions'));
    }

    public function update(SaveCreditNoteRequest $request, CreditNote $creditNote, SaveCreditNote $save)
    {
        $save->update($creditNote, $request->validated());

        return redirect()->route('credit-notes.show', $creditNote)->with('success', 'Credit note updated.');
    }

    public function open(CreditNote $creditNote, OpenCreditNote $open)
    {
        try {
            $open->handle($creditNote);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', $creditNote->restock
            ? 'Credit note opened. The returned goods are back in stock, and the credit can be applied to invoices or refunded.'
            : 'Credit note is now open and can be applied to invoices or refunded.');
    }

    public function void(CreditNote $creditNote, VoidCreditNote $void)
    {
        try {
            $void->handle($creditNote);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', 'Credit note voided.');
    }

    /** The apply form is on the credit note's page. */
    public function showApply(CreditNote $creditNote)
    {
        return redirect()->to(route('credit-notes.show', $creditNote).'#apply');
    }

    /**
     * Apply credit note to an invoice.
     */
    public function apply(Request $request, CreditNote $creditNote)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'amount' => 'required|numeric|min:0.01|max:'.$creditNote->balance,
        ]);

        try {
            $invoice = DB::transaction(function () use ($creditNote, $validated) {
                $creditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);
                $invoice = Invoice::lockForUpdate()->findOrFail($validated['invoice_id']);
                $creditNote->applyToInvoice($invoice, (float) $validated['amount']);

                return $invoice;
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', 'Applied '.number_format((float) $validated['amount'], 2)." to invoice {$invoice->invoice_number}.");
    }

    /** Pay the customer back out of the credit. */
    public function refund(Request $request, CreditNote $creditNote, RefundCreditNote $refund)
    {
        $validated = $request->validate([
            'refund_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,check,other',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $refund->handle($creditNote, $validated, auth()->id());

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', 'Refund of '.number_format((float) $validated['amount'], 2).' recorded.');
    }

    public function print(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'items.item', 'invoice']);
        $tenant = auth()->user()->tenant;

        return view('credit-notes.print', compact('creditNote', 'tenant'));
    }

    public function pdf(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'items.item', 'invoice']);
        $tenant = auth()->user()->tenant;

        return Pdf::loadView('credit-notes.print', ['creditNote' => $creditNote, 'tenant' => $tenant, 'forPdf' => true])
            ->download("credit-note-{$creditNote->credit_note_number}.pdf");
    }

    public function destroy(CreditNote $creditNote, DeleteCreditNote $delete)
    {
        if ($reason = $delete->blockedBecause($creditNote)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($creditNote);

        return redirect()->route('credit-notes.index')->with('success', 'Credit note deleted.');
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
