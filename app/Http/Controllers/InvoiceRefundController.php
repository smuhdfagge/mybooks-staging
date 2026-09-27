<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceRefundController extends Controller
{
    /**
     * Display a listing of refunds.
     */
    public function index()
    {
        return view('invoices.refunds.index');
    }

    /**
     * Show the form for creating a new refund.
     */
    public function create(Invoice $invoice)
    {
        // Check if invoice has any payments that can be refunded
        if ($invoice->amount_paid <= 0) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'This invoice has no payments to refund.');
        }

        // Calculate maximum refundable amount
        $maxRefundable = $invoice->amount_paid - ($invoice->total_refunded ?? 0);
        
        if ($maxRefundable <= 0) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'This invoice has already been fully refunded.');
        }

        $reasons = InvoiceRefund::REASONS;
        $methods = InvoiceRefund::METHODS;

        return view('invoices.refunds.create', compact('invoice', 'maxRefundable', 'reasons', 'methods'));
    }

    /**
     * Store a newly created refund.
     */
    public function store(Request $request, Invoice $invoice)
    {
        $maxRefundable = $invoice->amount_paid - ($invoice->total_refunded ?? 0);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $maxRefundable],
            'refund_date' => ['required', 'date', 'before_or_equal:today'],
            'refund_method' => ['required', Rule::in(array_keys(InvoiceRefund::METHODS))],
            'reason' => ['nullable', Rule::in(array_keys(InvoiceRefund::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            DB::beginTransaction();

            $refund = InvoiceRefund::create([
                'tenant_id' => auth()->user()->tenant_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'refund_number' => InvoiceRefund::generateNumber(auth()->user()->tenant_id),
                'refund_date' => $validated['refund_date'],
                'amount' => $validated['amount'],
                'refund_method' => $validated['refund_method'],
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            // Process the refund immediately (or you could have an approval workflow)
            $refund->process();

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', "Refund {$refund->refund_number} has been processed successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to process refund: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified refund.
     */
    public function show(InvoiceRefund $refund)
    {
        $refund->load(['invoice', 'customer', 'createdBy', 'approvedBy', 'journal.entries.account']);
        return view('invoices.refunds.show', compact('refund'));
    }

    /**
     * Cancel a refund.
     */
    public function cancel(InvoiceRefund $refund)
    {
        if ($refund->status === 'cancelled') {
            return redirect()->back()->with('error', 'This refund is already cancelled.');
        }

        try {
            DB::beginTransaction();
            
            $refund->cancel();
            
            DB::commit();

            return redirect()->route('invoices.show', $refund->invoice)
                ->with('success', "Refund {$refund->refund_number} has been cancelled.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to cancel refund: ' . $e->getMessage());
        }
    }

    /**
     * Print refund receipt.
     */
    public function print(InvoiceRefund $refund)
    {
        $refund->load(['invoice', 'customer', 'createdBy']);
        $tenant = auth()->user()->tenant;
        
        return view('invoices.refunds.print', compact('refund', 'tenant'));
    }
}
