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
use Illuminate\Validation\ValidationException;

class SyncController extends BaseApiController
{
    /** Most rows sent per entity per request (I2). */
    private const MAX_PAGE = 1000;

    /**
     * Permission needed to sync each entity. Sync used to check only
     * "view settings", so anyone with that could download invoices, bills,
     * payments and accounts they are not allowed to see (finding I1).
     */
    private const PERMISSIONS = [
        'customers' => 'view customers',
        'vendors' => 'view vendors',
        'items' => 'view items',
        'accounts' => 'view chart-of-accounts',
        'tax_rates' => 'view tax-rates',
        'invoices' => 'view invoices',
        'bills' => 'view bills',
        'expenses' => 'view expenses',
        'payments_received' => 'view payments-received',
        'payments_made' => 'view payments-made',
    ];

    /**
     * The requested entities the user may see (unknown names are dropped).
     *
     * @param  array<int, string>  $entities
     * @return array<int, string>
     */
    private function allowedEntities(Request $request, array $entities): array
    {
        return array_values(array_filter(
            array_map('trim', $entities),
            fn ($entity) => isset(self::PERMISSIONS[$entity]) && $request->user()->can(self::PERMISSIONS[$entity])
        ));
    }

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
            'cursors' => 'nullable|array', // cursors[customers]=... from a previous page (I2)
            'cursors.*' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_PAGE,
        ]);

        $tenantId = $this->getTenantId();
        $updatedSince = $request->get('updated_since')
            ? Carbon::parse($request->get('updated_since'))
            : null;

        // Parse requested entities (default: all)
        $requestedEntities = $request->get('entities')
            ? explode(',', $request->get('entities'))
            : array_keys(self::PERMISSIONS);
        $requestedEntities = $this->allowedEntities($request, $requestedEntities);

        $pages = [];
        $syncTimestamp = now()->toIso8601String();
        $limit = (int) $request->input('limit', self::MAX_PAGE);

        // Customers
        if (in_array('customers', $requestedEntities)) {
            $pages['customers'] = $this->getSyncPage(
                Customer::class,
                CustomerResource::class,
                $tenantId,
                $updatedSince,
                [],
                $request->input('cursors.customers'),
                $limit
            );
        }

        // Vendors
        if (in_array('vendors', $requestedEntities)) {
            $pages['vendors'] = $this->getSyncPage(
                Vendor::class,
                VendorResource::class,
                $tenantId,
                $updatedSince,
                [],
                $request->input('cursors.vendors'),
                $limit
            );
        }

        // Items
        if (in_array('items', $requestedEntities)) {
            $pages['items'] = $this->getSyncPage(
                Item::class,
                ItemResource::class,
                $tenantId,
                $updatedSince,
                ['category', 'inventory'],
                $request->input('cursors.items'),
                $limit
            );
        }

        // Chart of Accounts
        if (in_array('accounts', $requestedEntities)) {
            $pages['accounts'] = $this->getSyncPage(
                ChartOfAccount::class,
                ChartOfAccountResource::class,
                $tenantId,
                $updatedSince,
                [],
                $request->input('cursors.accounts'),
                $limit
            );
        }

        // Tax Rates
        if (in_array('tax_rates', $requestedEntities)) {
            $pages['tax_rates'] = $this->getSyncPage(
                TaxRate::class,
                TaxRateResource::class,
                $tenantId,
                $updatedSince,
                [],
                $request->input('cursors.tax_rates'),
                $limit
            );
        }

        // Invoices (with items and customer)
        if (in_array('invoices', $requestedEntities)) {
            $pages['invoices'] = $this->getSyncPage(
                Invoice::class,
                InvoiceResource::class,
                $tenantId,
                $updatedSince,
                ['customer', 'items.item'],
                $request->input('cursors.invoices'),
                $limit
            );
        }

        // Bills (with items and vendor)
        if (in_array('bills', $requestedEntities)) {
            $pages['bills'] = $this->getSyncPage(
                Bill::class,
                BillResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'items.item'],
                $request->input('cursors.bills'),
                $limit
            );
        }

        // Expenses
        if (in_array('expenses', $requestedEntities)) {
            $pages['expenses'] = $this->getSyncPage(
                Expense::class,
                ExpenseResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'expenseAccount'],
                $request->input('cursors.expenses'),
                $limit
            );
        }

        // Payments Received
        if (in_array('payments_received', $requestedEntities)) {
            $pages['payments_received'] = $this->getSyncPage(
                PaymentReceived::class,
                PaymentReceivedResource::class,
                $tenantId,
                $updatedSince,
                ['customer', 'invoice'],
                $request->input('cursors.payments_received'),
                $limit
            );
        }

        // Payments Made
        if (in_array('payments_made', $requestedEntities)) {
            $pages['payments_made'] = $this->getSyncPage(
                PaymentMade::class,
                PaymentMadeResource::class,
                $tenantId,
                $updatedSince,
                ['vendor', 'bill'],
                $request->input('cursors.payments_made'),
                $limit
            );
        }

        $data = array_map(fn ($page) => $page['data'], $pages);
        $paging = array_map(fn ($page) => [
            'count' => count($page['data']),
            'has_more' => $page['has_more'],
            'next_cursor' => $page['next_cursor'],
        ], $pages);
        $hasMore = in_array(true, array_column($pages, 'has_more'), true);

        return $this->success([
            'sync_timestamp' => $hasMore ? $this->resumeTimestamp($pages) : $syncTimestamp,
            'is_full_sync' => $updatedSince === null,
            'has_more' => $hasMore,
            'paging' => $paging,
            'data' => $data,
        ], $hasMore
            ? 'Partial sync: more records to fetch'
            : ($updatedSince ? 'Incremental sync completed' : 'Full sync completed'));
    }

    /**
     * Get sync data for a specific entity
     */
    public function entity(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'updated_since' => 'nullable|date',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_PAGE,
        ]);

        $tenantId = $this->getTenantId();
        $updatedSince = $request->get('updated_since')
            ? Carbon::parse($request->get('updated_since'))
            : null;
        $syncTimestamp = now()->toIso8601String();

        $entityConfig = $this->getEntityConfig($entity);

        if (! $entityConfig) {
            return $this->notFound("Entity '{$entity}' not found");
        }

        if (! $request->user()->can(self::PERMISSIONS[$entity])) {
            return $this->forbidden("You do not have permission to sync {$entity}");
        }

        $page = $this->getSyncPage(
            $entityConfig['model'],
            $entityConfig['resource'],
            $tenantId,
            $updatedSince,
            $entityConfig['relations'] ?? [],
            $request->input('cursor'),
            (int) $request->input('limit', self::MAX_PAGE)
        );

        return $this->success([
            'sync_timestamp' => $page['has_more'] ? $this->resumeTimestamp([$page]) : $syncTimestamp,
            'is_full_sync' => $updatedSince === null,
            'entity' => $entity,
            'count' => count($page['data']),
            'has_more' => $page['has_more'],
            'next_cursor' => $page['next_cursor'],
            'data' => $page['data'],
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
        $requestedEntities = $this->allowedEntities($request, $requestedEntities);

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
        $allowed = array_flip($this->allowedEntities($request, array_keys(self::PERMISSIONS)));

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
            'counts' => array_intersect_key($counts, $allowed),
            'last_updated' => array_intersect_key($lastUpdated, $allowed),
        ]);
    }

    /**
     * Get one page of sync data for a model, oldest change first.
     *
     * Sync used to return the newest 1,000 rows with a "synced up to" time,
     * so a phone that saved that time never got the older rows (I2). Now
     * rows come in (updated_at, id) order and a cursor points past the last
     * row sent, so every row is reached however many share a timestamp.
     *
     * @return array{data: array<int, mixed>, has_more: bool, next_cursor: ?string, last_updated_at: ?string}
     */
    private function getSyncPage(
        string $modelClass,
        string $resourceClass,
        int $tenantId,
        ?Carbon $updatedSince,
        array $relations = [],
        ?string $cursor = null,
        int $limit = self::MAX_PAGE
    ): array {
        $limit = min(max($limit, 1), self::MAX_PAGE);
        $query = $modelClass::where('tenant_id', $tenantId);

        $after = $cursor !== null && $cursor !== '' ? $this->decodeCursor($cursor) : null;
        if ($after !== null) {
            // The cursor already lies after updated_since, so it replaces it.
            $query->where(function ($q) use ($after) {
                $q->where('updated_at', '>', $after['u'])
                    ->orWhere(fn ($q) => $q->where('updated_at', $after['u'])->where('id', '>', $after['i']));
            });
        } elseif ($updatedSince) {
            $query->where('updated_at', '>=', $updatedSince);
        }

        if (! empty($relations)) {
            $query->with($relations);
        }

        // Customer and vendor balances in the same query (P4).
        if (method_exists($modelClass, 'scopeWithBalances')) {
            $query->withBalances();
        }

        // One extra row tells us whether there is another page.
        $records = $query->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $records->count() > $limit;
        if ($hasMore) {
            $records = $records->take($limit);
        }
        $last = $records->last();
        $lastUpdated = $last?->getRawOriginal('updated_at');

        return [
            'data' => $resourceClass::collection($records)->resolve(),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore && $last !== null ? $this->encodeCursor((string) $lastUpdated, (int) $last->getKey()) : null,
            'last_updated_at' => $lastUpdated !== null ? (string) $lastUpdated : null,
        ];
    }

    /**
     * On a partial page, "sync_timestamp" is the oldest point still to be
     * fetched, so an older client that only saves the timestamp resumes
     * from there instead of skipping rows (I2).
     *
     * @param  array<int|string, array{has_more: bool, last_updated_at: ?string}>  $pages
     */
    private function resumeTimestamp(array $pages): ?string
    {
        $times = array_filter(array_map(
            fn ($page) => $page['has_more'] ? $page['last_updated_at'] : null,
            $pages
        ));

        return $times === [] ? null : Carbon::parse(min($times))->toIso8601String();
    }

    private function encodeCursor(string $updatedAt, int $id): string
    {
        return rtrim(strtr(base64_encode((string) json_encode(['u' => $updatedAt, 'i' => $id])), '+/', '-_'), '=');
    }

    /**
     * @return array{u: string, i: int}
     */
    private function decodeCursor(string $cursor): array
    {
        $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        if (! is_array($decoded) || ! is_string($decoded['u'] ?? null) || ! is_int($decoded['i'] ?? null)
            || strtotime($decoded['u']) === false) {
            throw ValidationException::withMessages(['cursor' => 'The cursor is invalid.']);
        }

        return ['u' => $decoded['u'], 'i' => $decoded['i']];
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
