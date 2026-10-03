<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Services\Accounting\VatTreatment;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreditNoteController extends Controller
{
    public function index()
    {
        return view('credit-notes.index');
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        // Customers and items are searched as you type through the
        // lookup routes (P9), so they are not all loaded here.
        $creditNoteNumber = CreditNote::previewNumber($tenantId);

        $invoice = null;
        if ($request->has('invoice_id')) {
            $invoice = Invoice::with('items.item')->findOrFail($request->invoice_id);
        }

        $reasons = CreditNote::REASONS;

        return view('credit-notes.create', compact('creditNoteNumber', 'invoice', 'reasons'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'credit_note_date' => 'required|date',
            'reason' => ['nullable', Rule::in(array_keys(CreditNote::REASONS))],
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.vat_treatment' => ['nullable', Rule::in(VatTreatment::ALL)],
        ]);

        if (! empty($validated['invoice_id'])
            && (int) Invoice::whereKey($validated['invoice_id'])->value('customer_id') !== (int) $validated['customer_id']) {
            return back()->withInput()->withErrors(['invoice_id' => 'That invoice belongs to a different customer.']);
        }

        $creditNote = DB::transaction(function () use ($tenantId, $validated) {
            $cn = CreditNote::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'],
                'invoice_id' => $validated['invoice_id'] ?? null,
                'credit_note_number' => CreditNote::generateNumber($tenantId),
                'credit_note_date' => $validated['credit_note_date'],
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['items'] as $itemData) {
                $taxRate = $itemData['tax_rate'] ?? 0;
                $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                $taxAmount = $lineTotal * ($taxRate / 100);

                CreditNoteItem::create([
                    'credit_note_id' => $cn->id,
                    'item_id' => $itemData['item_id'] ?? null,
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'vat_treatment' => $itemData['vat_treatment'] ?? null,
                    'total' => $lineTotal + $taxAmount,
                ]);

                $subtotal += $lineTotal;
                $totalTax += $taxAmount;
            }

            $cn->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
                'balance' => $subtotal + $totalTax,
            ]);

            return $cn;
        });

        return redirect()->route('credit-notes.show', $creditNote)->with('success', 'Credit note created.');
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'invoice', 'items.item', 'applications.invoice', 'createdBy']);

        return view('credit-notes.show', compact('creditNote'));
    }

    public function open(CreditNote $creditNote)
    {
        if (! $creditNote->open()) {
            return redirect()->back()->with('error', 'Only draft credit notes can be opened.');
        }

        return redirect()->back()->with('success', 'Credit note is now open and can be applied to invoices.');
    }

    public function void(CreditNote $creditNote)
    {
        if (! $creditNote->void()) {
            return redirect()->back()->with('error', 'Cannot void a credit note that has been applied, or is already void.');
        }

        return redirect()->back()->with('success', 'Credit note voided.');
    }

    /**
     * Show form to apply credit note to an invoice.
     */
    public function showApply(CreditNote $creditNote)
    {
        if ($creditNote->status !== CreditNote::STATUS_OPEN || $creditNote->balance <= 0) {
            return redirect()->back()->with('error', 'This credit note has no available balance to apply.');
        }

        $invoices = Invoice::where('customer_id', $creditNote->customer_id)
            ->where('balance_due', '>', 0)
            ->whereIn('status', ['unpaid', 'partial', 'sent', 'overdue'])
            ->get();

        return view('credit-notes.apply', compact('creditNote', 'invoices'));
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
            ->with('success', "Applied {$validated['amount']} to Invoice {$invoice->invoice_number}.");
    }

    public function destroy(CreditNote $creditNote)
    {
        if ($creditNote->total_applied > 0) {
            return redirect()->back()->with('error', 'Cannot delete a credit note with applications.');
        }

        DB::transaction(function () use ($creditNote) {
            // An opened credit note has a journal; reverse it (N5)
            if ($creditNote->status !== CreditNote::STATUS_DRAFT) {
                app(JournalService::class)
                    ->reverseDocumentJournal(CreditNote::class, $creditNote->id, 'Credit note deleted');
            }
            $creditNote->items()->delete();
            $creditNote->delete();
        });

        return redirect()->route('credit-notes.index')->with('success', 'Credit note deleted.');
    }
}
