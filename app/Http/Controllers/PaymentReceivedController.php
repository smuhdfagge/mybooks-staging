<?php

namespace App\Http\Controllers;

use App\Actions\Payments\DeletePaymentReceived;
use App\Actions\Payments\RecordPaymentReceived;
use App\Http\Requests\StorePaymentReceivedRequest;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceived;
use App\Services\BankService;
use App\Services\NotificationService;
use App\Services\PaymentValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentReceivedController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected BankService $bankService
    ) {}

    public function index()
    {
        return view('payments-received.index');
    }

    public function create(Request $request)
    {
        $customers = Customer::where('is_active', true)->get();
        $invoiceId = $request->get('invoice_id');
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;
        $paymentNumber = PaymentReceived::previewNumber(auth()->user()->tenant_id);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        // Get all unpaid invoices for dynamic filtering
        $unpaidInvoices = Invoice::whereIn('status', ['draft', 'sent', 'unpaid', 'partial'])
            ->select('id', 'customer_id', 'invoice_number', 'balance_due', 'total')
            ->get();

        // Get customer deposits for applying to invoices
        $customerDeposits = PaymentReceived::where('is_deposit', true)
            ->where('unused_amount', '>', 0)
            ->select('id', 'customer_id', 'payment_number', 'unused_amount', 'payment_date')
            ->get();

        return view('payments-received.create', compact('customers', 'invoice', 'paymentNumber', 'unpaidInvoices', 'customerDeposits', 'banks'));
    }

    public function store(StorePaymentReceivedRequest $request, RecordPaymentReceived $record)
    {
        // Same rules as the API (R3): checks, bank balance, confirmation email.
        $payment = $record->handle(auth()->user()->tenant_id, $request->validated(), auth()->id());

        $message = match (true) {
            (bool) $payment->is_deposit => 'Customer deposit recorded.',
            $request->filled('apply_deposit_id') && (float) $request->input('deposit_amount') > 0 => 'Deposit applied to invoice successfully.',
            default => 'Payment recorded.',
        };

        return redirect()->route('payments-received.show', $payment)->with('success', $message);
    }

    public function show(PaymentReceived $paymentReceived)
    {
        $paymentReceived->load(['customer', 'invoice', 'journal.entries.account', 'depositApplications.invoice']);

        // If this is a deposit, also load the customer's deposit balance
        if ($paymentReceived->is_deposit) {
            $paymentReceived->load('customer');
        }

        return view('payments-received.show', compact('paymentReceived'));
    }

    public function edit(PaymentReceived $paymentReceived)
    {
        $customers = Customer::where('is_active', true)->get();
        $invoices = Invoice::where('customer_id', $paymentReceived->customer_id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('payments-received.edit', compact('paymentReceived', 'customers', 'invoices', 'banks'));
    }

    public function update(Request $request, PaymentReceived $paymentReceived)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // The new amount may not exceed what the invoice still owes, counting
        // this payment's current amount as available again (M5).
        if (! $paymentReceived->is_deposit && $paymentReceived->invoice) {
            $errors = PaymentValidation::forInvoice(
                $paymentReceived->invoice,
                $paymentReceived->customer_id,
                (float) $validated['amount'],
                (float) $paymentReceived->amount
            );
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        // Withholding tax recorded on the payment stays as it is (tax pack 2).
        $wht = (float) $paymentReceived->wht_amount;
        if ($wht > 0 && (float) $validated['amount'] <= $wht) {
            throw ValidationException::withMessages(['amount' => 'The amount must be more than the withholding tax on this payment ('.number_format($wht, 2).').']);
        }

        DB::transaction(function () use ($paymentReceived, $validated, $wht) {
            // If this is a deposit, update unused_amount proportionally
            if ($paymentReceived->is_deposit) {
                $amountDiff = $validated['amount'] - $paymentReceived->amount;
                $newUnusedAmount = max(0, $paymentReceived->unused_amount + $amountDiff);
                $validated['unused_amount'] = $newUnusedAmount;
            }

            $this->bankService->adjustOnUpdate(
                $paymentReceived->bank_id,
                $paymentReceived->cashAmount(),
                $validated['bank_id'] ?? null,
                round((float) $validated['amount'] - $wht, 2),
                'incoming',
                "Payment received #{$paymentReceived->payment_number} updated"
            );

            $paymentReceived->update($validated);
        });

        return redirect()->route('payments-received.show', $paymentReceived)->with('success', 'Payment updated.');
    }

    public function destroy(PaymentReceived $paymentReceived, DeletePaymentReceived $delete)
    {
        // Same rules as the API (R3).
        if ($reason = $delete->blockedBecause($paymentReceived)) {
            return redirect()->back()->with('error', $reason);
        }

        $delete->handle($paymentReceived);

        return redirect()->route('payments-received.index')->with('success', 'Payment deleted.');
    }

    /**
     * Apply a deposit to an invoice
     */
    public function applyDeposit(Request $request, PaymentReceived $paymentReceived)
    {
        if (! $paymentReceived->is_deposit) {
            return redirect()->back()->with('error', 'This payment is not a deposit.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'amount' => 'required|numeric|min:0.01|max:'.$paymentReceived->unused_amount,
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        // Same customer, payable invoice, not more than is owed (M5)
        $errors = PaymentValidation::forDepositApplication(
            $paymentReceived, $invoice, $paymentReceived->customer_id, (float) $validated['amount'], 0.0
        );
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $application = $paymentReceived->applyToInvoice($invoice, $validated['amount'], $validated['notes']);

            return redirect()->route('payments-received.show', $paymentReceived)
                ->with('success', 'Deposit applied to invoice successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show form to apply a deposit to invoices
     */
    public function showApplyDeposit(PaymentReceived $paymentReceived)
    {
        if (! $paymentReceived->is_deposit || $paymentReceived->unused_amount <= 0) {
            return redirect()->route('payments-received.show', $paymentReceived)
                ->with('error', 'This deposit has no available balance to apply.');
        }

        $unpaidInvoices = Invoice::where('customer_id', $paymentReceived->customer_id)
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->get();

        return view('payments-received.apply-deposit', compact('paymentReceived', 'unpaidInvoices'));
    }
}
