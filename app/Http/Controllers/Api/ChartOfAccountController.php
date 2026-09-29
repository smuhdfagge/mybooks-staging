<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreChartOfAccountRequest;
use App\Http\Requests\UpdateChartOfAccountRequest;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountController extends BaseApiController
{
    /**
     * Get all chart of accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChartOfAccount::with(['parent', 'children']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('account_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by sub_type
        if ($subType = $request->input('sub_type')) {
            $query->where('sub_type', $subType);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter root accounts only
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['account_code', 'name', 'type', 'sub_type', 'is_active', 'current_balance', 'created_at', 'updated_at'],
            'account_code'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $accounts = $query->paginate($this->validatedPerPage($request, 50));

        return $this->paginated($accounts->through(fn ($account) => new ChartOfAccountResource($account)));
    }

    /**
     * Get a specific account
     */
    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $chartOfAccount->load(['parent', 'children']);

        return $this->success(new ChartOfAccountResource($chartOfAccount));
    }

    /**
     * Create a new account
     */
    public function store(StoreChartOfAccountRequest $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validated();

        $validated['tenant_id'] = $tenantId;
        $validated['current_balance'] = $validated['opening_balance'] ?? 0;

        $account = ChartOfAccount::create($validated);
        $account->load(['parent']);

        return $this->created(new ChartOfAccountResource($account), 'Account created successfully');
    }

    /**
     * Update an account
     */
    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        if ($chartOfAccount->is_system) {
            return $this->forbidden('Cannot modify system accounts');
        }

        $validated = $request->validated();

        $chartOfAccount->update($validated);
        $chartOfAccount->load(['parent', 'children']);

        return $this->success(new ChartOfAccountResource($chartOfAccount), 'Account updated successfully');
    }

    /**
     * Delete an account
     */
    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        if ($chartOfAccount->is_system) {
            return $this->forbidden('Cannot delete system accounts');
        }

        if ($chartOfAccount->children()->exists()) {
            return $this->error('Cannot delete account with child accounts', 422);
        }

        if ($chartOfAccount->journalEntries()->exists()) {
            return $this->error('Cannot delete account with journal entries', 422);
        }

        $chartOfAccount->delete();

        return $this->success(null, 'Account deleted successfully');
    }

    /**
     * Get account types
     */
    public function types(): JsonResponse
    {
        return $this->success(ChartOfAccount::getTypes());
    }

    /**
     * Get account balance summary by type
     */
    public function balanceSummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $summary = [];
        foreach (ChartOfAccount::getTypes() as $type => $label) {
            $balance = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('type', $type)
                ->sum('current_balance');

            $summary[$type] = [
                'label' => $label,
                'balance' => (float) $balance,
                'count' => ChartOfAccount::where('tenant_id', $tenantId)
                    ->where('type', $type)
                    ->count(),
            ];
        }

        return $this->success($summary);
    }
}
