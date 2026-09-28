<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BillResource;
use App\Http\Resources\ChartOfAccountResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\PaymentMadeResource;
use App\Http\Resources\PaymentReceivedResource;
use App\Http\Resources\TaxRateResource;
use App\Http\Resources\VendorResource;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\TaxRate;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends BaseApiController
{
    /**
     * Get all data modified since a given timestamp for offline sync
     *
     * Usage: GET /api/v1/sync?updated_since=2026-01-10T00:00:00Z
     * First sync (no timestamp): returns all active data
     * Subsequent syncs: returns only modified records
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'updated_since' => 'nullable|date',
            'entities' => 'nullable|string', // comma-separated: customers,vendors,items
        ]);

        $tenantId = $this->getTenantId();
        $updatedSince = $request->get('updated_since')
            ? Carbon::parse($request->get('updated_since'))
            : null;

        // Parse requested entities (default: all)
        $requestedEntities = $request->get('entities')
            ? explode(',', $request->get('entities'))
            : ['customers', 'vendors', 'items', 'accounts', 'tax_rates', 'invoices', 'bills', 'expenses', 'payments_received', 'payments_made'];

        $data = [];
        $syncTimestamp = now()->toIso8601String();

        // Customers
        if (in_array('customers', $requestedEntities)) {
            $data['customers'] = $this->getSyncData(
                Customer::class,
                CustomerResource::class,
                $tenantId,
                $updatedSince
            );
        }

        // Vendors
        if (in_array('vendors', $requestedEntities)) {
            $data['vendors'] = $this->getSyncData(
                Vendor::class,
                VendorResource::class,
                $tenantId,
                $updatedSince
            );
        }

        // Items
        if (in_array('items', $requestedEntities)) {
            $data['items'] = $this->getSyncData(
                Item::class,
                ItemResource::class,
                $tenantId,
                $updatedSince,
                ['category', 'inventory']
            );
        }

        // Chart of Accounts
        if (in_array('accounts', $requestedEntities)) {
            $data['accounts'] = $this->getSyncData(
                ChartOfAccount::class,
                ChartOfAccountResource::class,
                $tenantId,
                $updatedSince
            );
        }

        // Tax Rates
        if (in_array('tax_rates', $requestedEntities)) {
            $data['tax_rates'] = $this->getSyncData(
                TaxRate::class,
                TaxRateResource::class,
                $tenantId,
                $updatedSince
            );
        }

        // Invoices (with items and customer)
        if (in_array('invoices', $requestedEntities)) {
            $data['invoices'] = $this->getSyncData(
                Invoice::class,
                InvoiceResource::class,
                $tenantId,
                $updatedSince,
                ['customer', 'items.item']
            );
        }

        // Bills (with items and vendor)
        if (in_array('bills', $requestedEntities)) {
            $data['bills'] = $this->getSyncData(
                Bill::class,
                BillResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'items.item']
            );
        }

        // Expenses
        if (in_array('expenses', $requestedEntities)) {
            $data['expenses'] = $this->getSyncData(
                Expense::class,
                ExpenseResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'expenseAccount']
            );
        }

        // Payments Received
        if (in_array('payments_received', $requestedEntities)) {
            $data['payments_received'] = $this->getSyncData(
                PaymentReceived::class,
                PaymentReceivedResource::class,
                $tenantId,
                $updatedSince,
                ['customer', 'invoice']
            );
        }

        // Payments Made
        if (in_array('payments_made', $requestedEntities)) {
            $data['payments_made'] = $this->getSyncData(
                PaymentMade::class,
                PaymentMadeResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'bill']
            );
        }

        return $this->success([
            'sync_timestamp' => $syncTimestamp,
            'is_full_sync' => $updatedSince === null,
            'data' => $data,
        ], $updatedSince ? 'Incremental sync completed' : 'Full sync completed');
    }

    /**
     * Get sync data for a specific entity
     */
    public function entity(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'updated_since' => 'nullable|date',
        ]);

        $tenantId = $this->getTenantId();
        $updatedSince = $request->get('updated_since')
            ? Carbon::parse($request->get('updated_since'))
            : null;

        $entityConfig = $this->getEntityConfig($entity);

        if (! $entityConfig) {
            return $this->notFound("Entity '{$entity}' not found");
        }

        $data = $this->getSyncData(
            $entityConfig['model'],
            $entityConfig['resource'],
            $tenantId,
            $updatedSince,
            $entityConfig['relations'] ?? []
        );

        return $this->success([
            'sync_timestamp' => now()->toIso8601String(),
            'is_full_sync' => $updatedSince === null,
            'entity' => $entity,
            'count' => count($data),
            'data' => $data,
        ]);
    }

    /**
     * Get deleted records since a timestamp
     * Mobile apps need to know what to remove from local storage
     */
    public function deleted(Request $request): JsonResponse
    {
        $request->validate([
            'deleted_since' => 'required|date',
            'entities' => 'nullable|string',
        ]);

        $tenantId = $this->getTenantId();
        $deletedSince = Carbon::parse($request->get('deleted_since'));

        $requestedEntities = $request->get('entities')
            ? explode(',', $request->get('entities'))
            : ['customers', 'vendors', 'items', 'invoices', 'bills', 'expenses'];

        $deleted = [];

        foreach ($requestedEntities as $entity) {
            $config = $this->getEntityConfig($entity);
            if ($config && method_exists($config['model'], 'onlyTrashed')) {
                $ids = $config['model']::onlyTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('deleted_at', '>=', $deletedSince)
                    ->pluck('id')
                    ->toArray();

                if (! empty($ids)) {
                    $deleted[$entity] = $ids;
                }
            }
        }

        return $this->success([
            'deleted_since' => $deletedSince->toIso8601String(),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Get sync status/summary without full data
     * Useful for checking if sync is needed
     */
    public function status(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $counts = [
            'customers' => Customer::where('tenant_id', $tenantId)->count(),
            'vendors' => Vendor::where('tenant_id', $tenantId)->count(),
            'items' => Item::where('tenant_id', $tenantId)->count(),
            'accounts' => ChartOfAccount::where('tenant_id', $tenantId)->count(),
            'tax_rates' => TaxRate::where('tenant_id', $tenantId)->count(),
            'invoices' => Invoice::where('tenant_id', $tenantId)->count(),
            'bills' => Bill::where('tenant_id', $tenantId)->count(),
            'expenses' => Expense::where('tenant_id', $tenantId)->count(),
        ];

        $lastUpdated = [
            'customers' => Customer::where('tenant_id', $tenantId)->max('updated_at'),
            'vendors' => Vendor::where('tenant_id', $tenantId)->max('updated_at'),
            'items' => Item::where('tenant_id', $tenantId)->max('updated_at'),
            'invoices' => Invoice::where('tenant_id', $tenantId)->max('updated_at'),
            'bills' => Bill::where('tenant_id', $tenantId)->max('updated_at'),
            'expenses' => Expense::where('tenant_id', $tenantId)->max('updated_at'),
        ];

        return $this->success([
            'server_time' => now()->toIso8601String(),
            'counts' => $counts,
            'last_updated' => $lastUpdated,
        ]);
    }

    /**
     * Helper: Get sync data for a model
     */
    private function getSyncData(
        string $modelClass,
        string $resourceClass,
        int $tenantId,
        ?Carbon $updatedSince,
        array $relations = []
    ): array {
        $query = $modelClass::where('tenant_id', $tenantId);

        if ($updatedSince) {
            $query->where('updated_at', '>=', $updatedSince);
        }

        if (! empty($relations)) {
            $query->with($relations);
        }

        // Limit results to prevent memory issues
        $records = $query->orderBy('updated_at', 'desc')
            ->limit(1000)
            ->get();

        return $resourceClass::collection($records)->resolve();
    }

    /**
     * Helper: Get entity configuration
     */
    private function getEntityConfig(string $entity): ?array
    {
        $config = [
            'customers' => [
                'model' => Customer::class,
                'resource' => CustomerResource::class,
            ],
            'vendors' => [
                'model' => Vendor::class,
                'resource' => VendorResource::class,
            ],
            'items' => [
                'model' => Item::class,
                'resource' => ItemResource::class,
                'relations' => ['category', 'inventory'],
            ],
            'accounts' => [
                'model' => ChartOfAccount::class,
                'resource' => ChartOfAccountResource::class,
            ],
            'tax_rates' => [
                'model' => TaxRate::class,
                'resource' => TaxRateResource::class,
            ],
            'invoices' => [
                'model' => Invoice::class,
                'resource' => InvoiceResource::class,
                'relations' => ['customer', 'items.item'],
            ],
            'bills' => [
                'model' => Bill::class,
                'resource' => BillResource::class,
                'relations' => ['vendor', 'items.item'],
            ],
            'expenses' => [
                'model' => Expense::class,
                'resource' => ExpenseResource::class,
                'relations' => ['vendor', 'expenseAccount'],
            ],
            'payments_received' => [
                'model' => PaymentReceived::class,
                'resource' => PaymentReceivedResource::class,
                'relations' => ['customer', 'invoice'],
            ],
            'payments_made' => [
                'model' => PaymentMade::class,
                'resource' => PaymentMadeResource::class,
                'relations' => ['vendor', 'bill'],
            ],
        ];

        return $config[$entity] ?? null;
    }
}
