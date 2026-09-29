<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends BaseApiController
{
    /**
     * Get all banks
     */
    public function index(Request $request): JsonResponse
    {
        $query = Bank::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        // Filter by account type
        if ($type = $request->input('account_type')) {
            $query->where('account_type', $type);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter primary accounts
        if ($request->boolean('is_primary')) {
            $query->where('is_primary', true);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['name', 'bank_name', 'account_type', 'is_active', 'is_primary', 'current_balance', 'created_at', 'updated_at'],
            'name'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $banks = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($banks->through(fn ($bank) => new BankResource($bank)));
    }

    /**
     * Get a specific bank
     */
    public function show(Bank $bank): JsonResponse
    {
        return $this->success(new BankResource($bank));
    }

    /**
     * Create a new bank account
     */
    public function store(StoreBankRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['tenant_id'] = $this->getTenantId();
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;

        // If this is set as primary, unset other primary accounts
        if ($validated['is_primary'] ?? false) {
            Bank::where('tenant_id', $validated['tenant_id'])
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $bank = Bank::create($validated);

        return $this->created(new BankResource($bank), 'Bank account created successfully');
    }

    /**
     * Update a bank account
     */
    public function update(UpdateBankRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();

        // If this is set as primary, unset other primary accounts
        if (($validated['is_primary'] ?? false) && ! $bank->is_primary) {
            Bank::where('tenant_id', $bank->tenant_id)
                ->where('id', '!=', $bank->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $bank->update($validated);

        return $this->success(new BankResource($bank), 'Bank account updated successfully');
    }

    /**
     * Delete a bank account
     */
    public function destroy(Bank $bank): JsonResponse
    {
        if ($bank->transactions()->exists()) {
            return $this->error('Cannot delete bank account with transactions', 422);
        }

        $bank->delete();

        return $this->success(null, 'Bank account deleted successfully');
    }

    /**
     * Get bank account types
     */
    public function types(): JsonResponse
    {
        return $this->success(Bank::getAccountTypes());
    }

    /**
     * Get bank accounts summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalBalance = Bank::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->sum('current_balance');

        $accountCount = Bank::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $byType = [];
        foreach (Bank::getAccountTypes() as $type => $label) {
            $balance = Bank::where('tenant_id', $tenantId)
                ->where('account_type', $type)
                ->where('is_active', true)
                ->sum('current_balance');

            $count = Bank::where('tenant_id', $tenantId)
                ->where('account_type', $type)
                ->where('is_active', true)
                ->count();

            $byType[$type] = [
                'label' => $label,
                'balance' => (float) $balance,
                'count' => $count,
            ];
        }

        return $this->success([
            'total_balance' => (float) $totalBalance,
            'account_count' => $accountCount,
            'by_type' => $byType,
        ]);
    }
}
