<?php

namespace App\Http\Controllers;

use App\Actions\CreditNotes\ApplyCreditNote;
use App\Actions\CreditNotes\DeleteCreditNote;
use App\Actions\CreditNotes\OpenCreditNote;
use App\Actions\CreditNotes\RefundCreditNote;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\CreditNotes\VoidCreditNote;
use App\Enums\CreditNoteStatus;
use App\Http\Requests\SaveCreditNoteRequest;
use App\Models\Bank;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Customer credit notes: goods returned by a customer, or a price
 * reduction. The rules live in App\Actions\CreditNotes.
 */
class CreditNoteController extends Controller
{
    public function index()
    {
        return view('credit-notes.index');
    }

    /**
     * New credit note. From an invoice (?invoice_id=) the customer, invoice
     * and lines are filled in at the price actually charged (after
     * discounts), less anything already credited, ready to cut down to what
     * is being credited.
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
            if (in_array($invoice->status, ['draft', 'cancelled', 'void'], true)) {
                return redirect()->route('invoices.show', $invoice)->with('error', "Invoice {$invoice->invoice_number} is {$invoice->status}: edit it instead of crediting it.");
            }
            $customer = $invoice->customer;
            $lines = $this->linesLeftOn($invoice);
            if ($lines === []) {
                return redirect()->route('invoices.show', $invoice)->with('error', "Invoice {$invoice->invoice_number} has already been fully credited.");
            }
        } elseif ($request->filled('customer_id')) {
            $customer = Customer::find($request->integer('customer_id'));
        }
        $customerOptions = $this->customerOptions(collect([$customer])->filter());

        return view('credit-notes.create', compact('creditNoteNumber', 'reasons', 'invoice', 'lines', 'customerOptions'));
    }

    public function store(SaveCreditNoteRequest $request, SaveCreditNote $save)
    {
        $note = $save->create(auth()->user()->tenant_id, $request->validated(), auth()->id());

        return redirect()->route('credit-notes.show', $note)->with('success', $note->isOpen()
            ? "Credit note {$note->credit_note_number} saved and posted.".($note->restock ? ' The returned goods are back in stock.' : '').' You can now apply it to an invoice or refund it.'
            : "Credit note {$note->credit_note_number} saved as a draft. Open it when you are ready to post it.");
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'invoice', 'items.item', 'applications.invoice', 'refunds.bank', 'createdBy', 'journals.entries.account']);

        $invoices = $creditNote->isOpen() && (float) $creditNote->balance > 0
            ? Invoice::where('customer_id', $creditNote->customer_id)
                ->where('balance_due', '>', 0)
                ->whereNotIn('status', ['draft', 'cancelled', 'void', 'paid'])
                ->orderBy('due_date')->get()
            : collect();
        $banks = Bank::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('credit-notes.show', compact('creditNote', 'invoices', 'banks'));
    }

    public function edit(CreditNote $creditNote)
    {
        if (! $creditNote->isDraft()) {
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
            ? 'Credit note opened and posted. The returned goods are back in stock, and the credit can be applied to invoices or refunded.'
            : 'Credit note opened and posted. The credit can be applied to invoices or refunded.');
    }

    public function void(CreditNote $creditNote, VoidCreditNote $void)
    {
        try {
            $void->handle($creditNote);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', $creditNote->restock && $creditNote->status !== CreditNoteStatus::Draft->value
            ? 'Credit note voided. Its journal was reversed and the returned goods were taken back out of stock.'
            : 'Credit note voided.');
    }

    /** The apply form is on the credit note's page. */
    public function showApply(CreditNote $creditNote)
    {
        return redirect()->to(route('credit-notes.show', $creditNote).'#apply');
    }

    /** Use (part of) the credit against one of the customer's unpaid invoices. */
    public function apply(Request $request, CreditNote $creditNote, ApplyCreditNote $apply)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        try {
            $apply->handle($creditNote, $invoice, (float) $validated['amount']);
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', 'Applied '.number_format((float) $validated['amount'], 2)." to invoice {$invoice->invoice_number}.");
    }

    /** Pay the customer back out of the credit. */
    public function refund(Request $request, CreditNote $creditNote, RefundCreditNote $refund)
    {
        $validated = $request->validate([
            'refund_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'mobile_money', 'other'])],
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund->handle($creditNote, $validated, auth()->id());

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', 'Refund of '.number_format((float) $validated['amount'], 2).' to the customer recorded.');
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

    /**
     * The invoice's lines as credit note lines: at the net price per unit
     * actually charged (after line and invoice discounts), less what earlier
     * credit notes already credited. Fully credited lines are left out.
     *
     * @return array<int, array<string, mixed>>
     */
    private function linesLeftOn(Invoice $invoice): array
    {
        $nets = SaveCreditNote::invoiceLineNets($invoice);
        $earlier = CreditNoteItem::whereHas('creditNote', fn ($q) => $q->where('invoice_id', $invoice->id)
            ->where('status', '!=', CreditNoteStatus::Void->value))->get();
        $key = fn ($itemId, $description) => $itemId ? 'item:'.(int) $itemId : 'text:'.mb_strtolower(trim((string) $description));
        $creditedNet = $earlier->groupBy(fn ($l) => $key($l->item_id, $l->description))->map(fn ($g) => (float) $g->sum(fn ($l) => $l->net()))->all();

        $lines = [];
        foreach ($invoice->items as $l) {
            /** @var InvoiceItem $l */
            $qty = (float) $l->quantity;
            if ($qty <= 0) {
                continue;
            }
            // Rounded down to the kobo, so a full credit is never more than was charged.
            $unitNet = floor(round($nets[$l->id] / $qty, 6) * 100) / 100;
            $k = $key($l->item_id, $l->description);
            // Earlier credits come off the first matching lines.
            $alreadyQty = $unitNet > 0 ? min($qty, round(($creditedNet[$k] ?? 0) / $unitNet, 2)) : 0;
            $creditedNet[$k] = max(0, ($creditedNet[$k] ?? 0) - $alreadyQty * $unitNet);
            if ($qty - $alreadyQty <= 0.0001) {
                continue;
            }
            $lines[] = [
                'item_id' => $l->item_id,
                'item_name' => $l->item?->name,
                'description' => $l->description,
                'quantity' => round($qty - $alreadyQty, 2),
                'unit_price' => $unitNet,
                'tax_rate' => (float) $l->tax_rate,
            ];
        }

        return $lines;
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
