<?php

namespace App\Livewire\SalesOrders;

use App\Actions\SalesOrders\DeleteSalesOrder;
use App\Enums\SalesOrderStatus;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Sales orders list (tables plan T2: the shared list design, see InvoicesTable).
 */
class SalesOrdersTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $customer = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['order_date', 'order_number', 'expected_date', 'total'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'customer'];
    }

    protected function rowRelations(): array
    {
        return ['customer:id,name'];
    }

    protected function baseQuery(): Builder
    {
        $query = SalesOrder::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('company_name', 'like', "%{$term}%")));
        }
        if ($this->customer !== '' && ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        return $this->applyPeriod($query, 'order_date', $this->period);
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'confirm' => 'edit sales-orders',
            'cancel' => 'edit sales-orders',
            'delete' => 'delete sales-orders',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one order.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'confirm':
                SalesOrder::whereIn('id', $this->selectedItems)
                    ->where('status', 'draft')
                    ->update(['status' => 'confirmed']);
                $this->successMessage = 'Successfully confirmed selected order(s).';
                break;

            case 'cancel':
                SalesOrder::whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'confirmed'])
                    ->update(['status' => 'cancelled']);
                $this->successMessage = 'Successfully cancelled selected order(s).';
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $orderId) {
                    $order = SalesOrder::find($orderId);
                    if (! $order) {
                        continue;
                    }

                    // Same rules as the web and API delete (R3).
                    $delete = app(DeleteSalesOrder::class);
                    if ($delete->blockedBecause($order)) {
                        $skippedCount++;

                        continue;
                    }

                    $delete->handle($order);
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} order(s). Skipped {$skippedCount} order(s) with invoices or delivery notes.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} order(s).";
                } else {
                    $this->errorMessage = 'Could not delete any orders. All selected orders have invoices or delivery notes.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function render()
    {
        $labels = [];
        foreach (SalesOrderStatus::cases() as $s) {
            $labels[$s->value] = ucfirst($s->value);
        }

        return view('livewire.sales-orders.sales-orders-table', [
            'orders' => $this->rows(),
            'tabs' => $this->statusTabs($labels, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
