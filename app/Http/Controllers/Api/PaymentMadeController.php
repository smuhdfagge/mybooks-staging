<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaymentMadeResource;
use App\Models\Bill;
use App\Models\PaymentMade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMadeController extends BaseApiController
{
    /**
     * Get all payments made
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMade::with(['vendor', 'bill', 'bank']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by vendor
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        // Filter by bill
        if ($billId = $request->input('bill_id')) {
            $query->where('bill_id', $billId);
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
            ['payment_number', 'payment_date', 'amount', 'payment_method', 'created_at', 'updated_at'],
            'created_at'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $payments = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($payments->through(fn ($payment) => new PaymentMadeResource($payment)));
    }

    /**
     * Get a specific payment
     */
    public function show(PaymentMade $paymentMade): JsonResponse
    {
        $paymentMade->load(['vendor', 'bill', 'bank', 'createdBy']);

        return $this->success(new PaymentMadeResource($paymentMade));
    }

    /**
     * Create a new payment made
     */
    public function store(\App\Http\Requests\StorePaymentMadeRequest $request, \App\Actions\Payments\RecordPaymentMade $record): JsonResponse
    {
        // Same rules and code as the web form (R3, Q5), including the bank balance.
        $payment = $record->handle($this->getTenantId(), $request->validated(), auth()->id());
        $payment->load(['vendor', 'bill', 'bank']);

        return $this->created(new PaymentMadeResource($payment), 'Payment recorded successfully');
    }

    /**
     * Delete a payment
     */
    public function destroy(PaymentMade $paymentMade, \App\Actions\Payments\DeletePaymentMade $delete): JsonResponse
    {
        $delete->handle($paymentMade);

        return $this->success(null, 'Payment deleted successfully');
    }

    /**
     * Get payment summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalPayments = PaymentMade::where('tenant_id', $tenantId)->count();
        $totalAmount = PaymentMade::where('tenant_id', $tenantId)->sum('amount');

        // Monthly breakdown
        $monthlyPayments = PaymentMade::where('tenant_id', $tenantId)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        return $this->success([
            'total_payments' => $totalPayments,
            'total_amount' => (float) $totalAmount,
            'monthly_payments' => (float) $monthlyPayments,
        ]);
    }
}
