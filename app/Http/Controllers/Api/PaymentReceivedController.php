<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaymentReceivedResource;
use App\Models\Invoice;
use App\Models\PaymentReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(\App\Http\Requests\StorePaymentReceivedRequest $request, \App\Actions\Payments\RecordPaymentReceived $record): JsonResponse
    {
        // Same rules and code as the web form (R3, Q5): the bank balance
        // is updated and deposits can be applied, which the API used to skip.
        $payment = $record->handle($this->getTenantId(), $request->validated(), auth()->id());
        $payment->load(['customer', 'invoice', 'bank']);

        return $this->created(new PaymentReceivedResource($payment), 'Payment recorded successfully');
    }

    /**
     * Delete a payment
     */
    public function destroy(PaymentReceived $paymentReceived, \App\Actions\Payments\DeletePaymentReceived $delete): JsonResponse
    {
        if ($reason = $delete->blockedBecause($paymentReceived)) {
            return $this->error($reason, 422);
        }

        $delete->handle($paymentReceived);

        return $this->success(null, 'Payment deleted successfully');
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
