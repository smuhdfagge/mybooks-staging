<?php

namespace App\Livewire\PurchaseOrders;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $status = '';

    public $vendor = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $perPage = 10;

    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'vendor' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingVendor()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'status', 'vendor', 'dateFrom', 'dateTo']);
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
        return PurchaseOrder::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
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
            'confirm' => 'edit purchase-orders',
            'cancel' => 'edit purchase-orders',
            'delete' => 'delete purchase-orders',
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

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'confirm':
                PurchaseOrder::whereIn('id', $this->selectedItems)
                    ->where('status', 'draft')
                    ->update(['status' => 'confirmed']);
                $this->successMessage = 'Successfully confirmed selected order(s).';
                break;

            case 'cancel':
                PurchaseOrder::whereIn('id', $this->selectedItems)
                    ->whereIn('status', ['draft', 'confirmed'])
                    ->update(['status' => 'cancelled']);
                $this->successMessage = 'Successfully cancelled selected order(s).';
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $orderId) {
                    $order = PurchaseOrder::find($orderId);
                    if (! $order) {
                        continue;
                    }

                    if ($order->bills()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $order->items()->delete();
                    $order->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} order(s). Skipped {$skippedCount} order(s) with associated bills.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} order(s).";
                } else {
                    $this->errorMessage = 'Could not delete any orders. All selected orders have associated bills.';
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
        $orders = PurchaseOrder::with(['vendor'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('vendor', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->vendor, function ($query) {
                $query->where('vendor_id', $this->vendor);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('order_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('order_date', '<=', $this->dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $vendors = Vendor::where('is_active', true)->orderBy('name')->get();

        return view('livewire.purchase-orders.purchase-orders-table', [
            'orders' => $orders,
            'vendors' => $vendors,
        ]);
    }
}
