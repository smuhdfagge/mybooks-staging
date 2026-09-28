<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaymentMadeResource;
use App\Models\Bill;
use App\Models\PaymentMade;
use App\Services\PaymentValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('tenant_id', $tenantId)],
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_id' => ['nullable', Rule::exists('banks', 'id')->where('tenant_id', $tenantId)],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Same checks as the web form (M5). The old strict !== comparison
        // failed whenever the vendor ID arrived as a string.
        if (! empty($validated['bill_id'])) {
            $errors = PaymentValidation::forBill(Bill::find($validated['bill_id']), $validated['vendor_id'], (float) $validated['amount']);
            if ($errors) {
                return $this->validationError(array_map(fn ($message) => [$message], $errors));
            }
        }

        $validated['payment_number'] = PaymentMade::generateNumber($tenantId);
        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $payment = PaymentMade::create($validated);

            // Update bill if linked
            if (! empty($validated['bill_id'])) {
                $payment->bill?->updateBalances();
            }

            DB::commit();

            $payment->load(['vendor', 'bill', 'bank']);

            return $this->created(new PaymentMadeResource($payment), 'Payment recorded successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to record payment: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete a payment
     */
    public function destroy(PaymentMade $paymentMade): JsonResponse
    {
        DB::beginTransaction();
        try {
            $bill = $paymentMade->bill;

            $paymentMade->delete();

            // Update bill balances
            if ($bill) {
                $bill->updateBalances();
            }

            DB::commit();

            return $this->success(null, 'Payment deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to delete payment: '.$e->getMessage(), 500);
        }
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
