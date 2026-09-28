<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceived;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Bank;
use App\Models\CustomerDepositApplication;
use App\Models\NotificationSetting;
use App\Services\BankService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\PaymentValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $paymentNumber = PaymentReceived::generateNumber(auth()->user()->tenant_id);
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

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_deposit' => 'boolean',
            'apply_deposit_id' => ['nullable', Rule::exists('payments_received', 'id')->where('tenant_id', $tenantId)],
            'deposit_amount' => 'nullable|numeric|min:0',
        ]);

        $isDeposit = $request->boolean('is_deposit');
        $applyDepositId = $validated['apply_deposit_id'] ?? null;
        $depositAmountToApply = $validated['deposit_amount'] ?? 0;

        // Business checks (M5): right customer, payable invoice, not more than
        // is owed, deposit has enough left. Shown as form errors.
        $invoice = ! empty($validated['invoice_id']) ? Invoice::find($validated['invoice_id']) : null;
        if ($applyDepositId && $depositAmountToApply > 0) {
            $errors = PaymentValidation::forDepositApplication(
                PaymentReceived::find($applyDepositId),
                $invoice,
                $validated['customer_id'],
                (float) $depositAmountToApply,
                (float) $validated['amount'] - (float) $depositAmountToApply
            );
        } elseif (! $isDeposit) {
            $errors = PaymentValidation::forInvoice($invoice, $validated['customer_id'], (float) $validated['amount']);
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($validated, $tenantId, $isDeposit, $applyDepositId, $depositAmountToApply, $request) {
            // If applying a deposit to an invoice
            if ($applyDepositId && $depositAmountToApply > 0 && !empty($validated['invoice_id'])) {
                $deposit = PaymentReceived::findOrFail($applyDepositId);
                $invoice = Invoice::findOrFail($validated['invoice_id']);
                
                // Validate the deposit can be applied
                if (!$deposit->is_deposit || $deposit->unused_amount < $depositAmountToApply) {
                    throw new \InvalidArgumentException('Invalid deposit or insufficient balance');
                }
                
                if ($invoice->balance_due < $depositAmountToApply) {
                    throw new \InvalidArgumentException('Deposit amount exceeds invoice balance');
                }
                
                // Apply the deposit
                $application = $deposit->applyToInvoice($invoice, $depositAmountToApply, $validated['notes'] ?? null);
                
                // If there's additional payment amount beyond the deposit
                $remainingAmount = $validated['amount'] - $depositAmountToApply;
                if ($remainingAmount > 0) {
                    $payment = PaymentReceived::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $validated['customer_id'],
                        'invoice_id' => $validated['invoice_id'],
                        'payment_number' => PaymentReceived::generateNumber($tenantId),
                        'payment_date' => $validated['payment_date'],
                        'amount' => $remainingAmount,
                        'payment_method' => $validated['payment_method'],
                        'bank_id' => $validated['bank_id'] ?? null,
                        'reference' => $validated['reference'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'is_deposit' => false,
                        'unused_amount' => 0,
                        'created_by' => auth()->id(),
                    ]);

                    $this->bankService->credit(
                        $validated['bank_id'] ?? null,
                        $remainingAmount,
                        "Payment received #{$payment->payment_number} (deposit application remainder)"
                    );
                    
                    return redirect()->route('payments-received.show', $payment)->with('success', 'Payment and deposit applied successfully.');
                }
                
                // Return the applied payment
                return redirect()->route('payments-received.show', $application->appliedPayment)->with('success', 'Deposit applied to invoice successfully.');
            }
            
            // Regular payment or new deposit
            $payment = PaymentReceived::create([
                'tenant_id' => $tenantId,
                'customer_id' => $validated['customer_id'],
                'invoice_id' => $isDeposit ? null : ($validated['invoice_id'] ?? null),
                'payment_number' => PaymentReceived::generateNumber($tenantId),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'bank_id' => $validated['bank_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_deposit' => $isDeposit,
                'unused_amount' => $isDeposit ? $validated['amount'] : 0,
                'created_by' => auth()->id(),
            ]);

            $this->bankService->credit(
                $validated['bank_id'] ?? null,
                $validated['amount'],
                "Payment received #{$payment->payment_number}"
            );

            // Send payment confirmation if enabled (not for deposits)
            if (!$isDeposit) {
                $settings = NotificationSetting::getForTenant($tenantId);
                if ($settings->send_payment_confirmation) {
                    $this->notificationService->sendPaymentConfirmation($payment);
                }
            }

            $message = $isDeposit ? 'Customer deposit recorded.' : 'Payment recorded.';
            return redirect()->route('payments-received.show', $payment)->with('success', $message);
        });
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

        DB::transaction(function () use ($paymentReceived, $validated) {
            // If this is a deposit, update unused_amount proportionally
            if ($paymentReceived->is_deposit) {
                $amountDiff = $validated['amount'] - $paymentReceived->amount;
                $newUnusedAmount = max(0, $paymentReceived->unused_amount + $amountDiff);
                $validated['unused_amount'] = $newUnusedAmount;
            }

            $this->bankService->adjustOnUpdate(
                $paymentReceived->bank_id,
                $paymentReceived->amount,
                $validated['bank_id'] ?? null,
                $validated['amount'],
                'incoming',
                "Payment received #{$paymentReceived->payment_number} updated"
            );

            $paymentReceived->update($validated);
        });

        return redirect()->route('payments-received.show', $paymentReceived)->with('success', 'Payment updated.');
    }

    public function destroy(PaymentReceived $paymentReceived)
    {
        // Check if this deposit has been applied to any invoices
        if ($paymentReceived->is_deposit && $paymentReceived->depositApplications()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete a deposit that has been applied to invoices.');
        }

        DB::transaction(function () use ($paymentReceived) {
            $this->bankService->debit(
                $paymentReceived->bank_id,
                $paymentReceived->amount,
                "Payment received #{$paymentReceived->payment_number} deleted"
            );

            $paymentReceived->delete();
        });

        return redirect()->route('payments-received.index')->with('success', 'Payment deleted.');
    }

    /**
     * Apply a deposit to an invoice
     */
    public function applyDeposit(Request $request, PaymentReceived $paymentReceived)
    {
        if (!$paymentReceived->is_deposit) {
            return redirect()->back()->with('error', 'This payment is not a deposit.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'amount' => 'required|numeric|min:0.01|max:' . $paymentReceived->unused_amount,
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
        if (!$paymentReceived->is_deposit || $paymentReceived->unused_amount <= 0) {
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
