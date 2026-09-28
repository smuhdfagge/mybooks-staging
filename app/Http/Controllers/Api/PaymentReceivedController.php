<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentReceived;
use App\Models\Invoice;
use App\Http\Resources\PaymentReceivedResource;
use Illuminate\Http\Request;
use App\Services\PaymentValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentReceivedController extends BaseApiController
{
    /**
     * Get all payments received
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentReceived::with(['customer', 'invoice', 'bank']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        // Filter by invoice
        if ($invoiceId = $request->input('invoice_id')) {
            $query->where('invoice_id', $invoiceId);
        }

        // Filter deposits only
        if ($request->boolean('is_deposit')) {
            $query->where('is_deposit', true);
        }

        // Filter by payment method
        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['payment_number', 'payment_date', 'amount', 'payment_method', 'is_deposit', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $payments = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($payments->through(fn ($payment) => new PaymentReceivedResource($payment)));
    }

    /**
     * Get a specific payment
     */
    public function show(PaymentReceived $paymentReceived): JsonResponse
    {
        $paymentReceived->load(['customer', 'invoice', 'bank', 'createdBy']);
        return $this->success(new PaymentReceivedResource($paymentReceived));
    }

    /**
     * Create a new payment received
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

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
        ]);

        // Same checks as the web form (M5). The old strict !== comparison
        // failed whenever the customer ID arrived as a string.
        if (!empty($validated['invoice_id']) && ! ($validated['is_deposit'] ?? false)) {
            $errors = PaymentValidation::forInvoice(
                Invoice::find($validated['invoice_id']),
                $validated['customer_id'],
                (float) $validated['amount']
            );
            if ($errors) {
                return $this->validationError(array_map(fn ($message) => [$message], $errors));
            }
        }

        $validated['payment_number'] = PaymentReceived::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();

        // Set unused_amount for deposits
        if ($validated['is_deposit'] ?? false) {
            $validated['unused_amount'] = $validated['amount'];
        }

        DB::beginTransaction();
        try {
            $payment = PaymentReceived::create($validated);

            // Update invoice if linked
            if (!empty($validated['invoice_id'])) {
                $payment->invoice?->updateBalances();
            }

            // Update customer deposit balance if it's a deposit
            if ($validated['is_deposit'] ?? false) {
                $payment->customer->updateDepositBalance();
            }

            DB::commit();

            $payment->load(['customer', 'invoice', 'bank']);
            return $this->created(new PaymentReceivedResource($payment), 'Payment recorded successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a payment
     */
    public function destroy(PaymentReceived $paymentReceived): JsonResponse
    {
        DB::beginTransaction();
        try {
            $invoice = $paymentReceived->invoice;
            $customer = $paymentReceived->customer;
            $isDeposit = $paymentReceived->is_deposit;

            $paymentReceived->delete();

            // Update invoice balances
            if ($invoice) {
                $invoice->updateBalances();
            }

            // Update customer deposit balance
            if ($isDeposit) {
                $customer->updateDepositBalance();
            }

            DB::commit();
            return $this->success(null, 'Payment deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalPayments = PaymentReceived::where('tenant_id', $tenantId)->count();
        $totalAmount = PaymentReceived::where('tenant_id', $tenantId)->sum('amount');
        $totalDeposits = PaymentReceived::where('tenant_id', $tenantId)
            ->where('is_deposit', true)
            ->sum('amount');
        $unusedDeposits = PaymentReceived::where('tenant_id', $tenantId)
            ->where('is_deposit', true)
            ->sum('unused_amount');

        // Monthly breakdown
        $monthlyPayments = PaymentReceived::where('tenant_id', $tenantId)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        return $this->success([
            'total_payments' => $totalPayments,
            'total_amount' => (float) $totalAmount,
            'total_deposits' => (float) $totalDeposits,
            'unused_deposits' => (float) $unusedDeposits,
            'monthly_payments' => (float) $monthlyPayments,
        ]);
    }
}
