<?php

namespace App\Livewire\SalesOrders;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Customer;
use App\Models\SalesOrder;
use Livewire\Component;
use Livewire\WithPagination;

class SalesOrdersTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $status = '';

    public $customer = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingCustomer()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'status', 'customer', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredOrderIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredOrderIds());
    }

    private function getFilteredOrderIds()
    {
        return SalesOrder::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('order_date', '<=', $this->dateTo))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
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

                    // Check if order has been invoiced
                    if ($order->invoices()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $order->items()->delete();
                    $order->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} order(s). Skipped {$skippedCount} order(s) with existing invoices.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} order(s).";
                } else {
                    $this->errorMessage = 'Could not delete any orders. All selected orders have existing invoices.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function render()
    {
        $orders = SalesOrder::with(['customer'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->customer, function ($query) {
                $query->where('customer_id', $this->customer);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('order_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('order_date', '<=', $this->dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('livewire.sales-orders.sales-orders-table', [
            'orders' => $orders,
            'customers' => $customers,
        ]);
    }
}
