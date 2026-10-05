<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BillResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\VendorResource;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends BaseApiController
{
    /**
     * Global search across all entities
     */
    public function global(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 5), 20);

        if (strlen($query) < 2) {
            return $this->error('Search query must be at least 2 characters', 422);
        }

        $tenantId = $this->getTenantId();
        $results = [];

        // Search Customers
        $customers = Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%");
            })
            ->take($limit)
            ->get();

        if ($customers->count() > 0) {
            $results['customers'] = CustomerResource::collection($customers);
        }

        // Search Vendors
        $vendors = Vendor::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%");
            })
            ->take($limit)
            ->get();

        if ($vendors->count() > 0) {
            $results['vendors'] = VendorResource::collection($vendors);
        }

        // Search Items
        $items = Item::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->take($limit)
            ->get();

        if ($items->count() > 0) {
            $results['items'] = ItemResource::collection($items);
        }

        // Search Invoices
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('invoice_number', 'like', "%{$query}%")
                    ->orWhere('reference', 'like', "%{$query}%")
                    ->orWhereHas('customer', function ($cq) use ($query) {
                        $cq->where('name', 'like', "%{$query}%");
                    });
            })
            ->with('customer:id,name')
            ->take($limit)
            ->get();

        if ($invoices->count() > 0) {
            $results['invoices'] = InvoiceResource::collection($invoices);
        }

        // Search Bills
        $bills = Bill::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('bill_number', 'like', "%{$query}%")
                    ->orWhere('vendor_bill_number', 'like', "%{$query}%")
                    ->orWhereHas('vendor', function ($vq) use ($query) {
                        $vq->where('name', 'like', "%{$query}%");
                    });
            })
            ->with('vendor:id,name')
            ->take($limit)
            ->get();

        if ($bills->count() > 0) {
            $results['bills'] = BillResource::collection($bills);
        }

        return $this->success([
            'query' => $query,
            'results' => $results,
            'counts' => [
                'customers' => $customers->count(),
                'vendors' => $vendors->count(),
                'items' => $items->count(),
                'invoices' => $invoices->count(),
                'bills' => $bills->count(),
            ],
        ]);
    }

    /**
     * Quick customer lookup for autocomplete
     */
    public function customers(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);
        $activeOnly = $request->boolean('active_only', true);

        $customers = Customer::where('tenant_id', $this->getTenantId())
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sq) use ($query) {
                    $sq->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('company_name', 'like', "%{$query}%");
                });
            })
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'email', 'company_name', 'phone', 'credit_limit', 'deposit_balance']);

        return $this->success($customers->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'company_name' => $c->company_name,
            'phone' => $c->phone,
            'credit_limit' => (float) $c->credit_limit,
            'deposit_balance' => (float) $c->deposit_balance,
        ]));
    }

    /**
     * Quick vendor lookup for autocomplete
     */
    public function vendors(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);
        $activeOnly = $request->boolean('active_only', true);

        $vendors = Vendor::where('tenant_id', $this->getTenantId())
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sq) use ($query) {
                    $sq->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('company_name', 'like', "%{$query}%");
                });
            })
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'email', 'company_name', 'phone']);

        return $this->success($vendors->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'email' => $v->email,
            'company_name' => $v->company_name,
            'phone' => $v->phone,
        ]));
    }

    /**
     * Quick item lookup for autocomplete
     */
    public function items(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);
        $activeOnly = $request->boolean('active_only', true);
        $type = $request->get('type'); // product, service
        $inStock = $request->boolean('in_stock', false);

        $items = Item::where('tenant_id', $this->getTenantId())
            ->with(['inventory', 'taxRate', 'taxGroup.taxRates'])
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sq) use ($query) {
                    $sq->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                });
            })
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($inStock, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('track_inventory', false)
                        ->orWhere('type', 'service')
                        ->orWhereRaw(Item::onHandSql().' - '.Item::onHandSql(null, 'reserved_quantity').' > 0');
                });
            })
            ->orderBy('name')
            ->take($limit)
            ->get();

        return $this->success($items->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'sku' => $i->sku,
            'type' => $i->type,
            'unit' => $i->unit,
            'selling_price' => (float) $i->selling_price,
            'cost_price' => (float) $i->cost_price,
            'tax_rate' => (float) $i->tax_rate,
            'is_taxable' => $i->is_taxable,
            'track_inventory' => $i->track_inventory,
            'available_quantity' => $i->inventory ? (float) $i->inventory->available_quantity : null,
            // Used by the invoice and bill forms' item search (P9).
            'description' => $i->description,
            'effective_tax_rate' => (float) ($i->effective_tax_rate ?? 0),
            'purchase_price' => (float) ($i->cost_price ?? $i->selling_price),
        ]));
    }

    /**
     * Quick account lookup for journal entries
     */
    public function accounts(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);
        $type = $request->get('type'); // asset, liability, equity, income, expense

        $accounts = ChartOfAccount::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sq) use ($query) {
                    $sq->where('name', 'like', "%{$query}%")
                        ->orWhere('account_code', 'like', "%{$query}%");
                });
            })
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('account_code')
            ->take($limit)
            ->get(['id', 'account_code', 'name', 'type', 'sub_type', 'current_balance']);

        return $this->success($accounts->map(fn ($a) => [
            'id' => $a->id,
            'account_code' => $a->account_code,
            'name' => $a->name,
            'type' => $a->type,
            'sub_type' => $a->sub_type,
            'current_balance' => (float) $a->current_balance,
            'display_name' => "{$a->account_code} - {$a->name}",
        ]));
    }
}
