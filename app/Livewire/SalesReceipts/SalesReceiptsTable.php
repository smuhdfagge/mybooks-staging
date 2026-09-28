<?php

namespace App\Livewire\SalesReceipts;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Customer;
use App\Models\SalesReceipt;
use Livewire\Component;
use Livewire\WithPagination;

class SalesReceiptsTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $paymentMethod = '';

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
        'paymentMethod' => ['except' => ''],
        'customer' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPaymentMethod()
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
        $this->reset(['search', 'paymentMethod', 'customer', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredReceiptIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredReceiptIds());
    }

    private function getFilteredReceiptIds()
    {
        return SalesReceipt::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('receipt_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('receipt_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('receipt_date', '<=', $this->dateTo))
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
            'delete' => 'delete sales-receipts',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one receipt.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'delete':
                SalesReceipt::whereIn('id', $this->selectedItems)->each(function ($receipt) {
                    $receipt->items()->delete();
                    $receipt->delete();
                });
                $this->successMessage = "Successfully deleted {$count} receipt(s).";
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
        $receipts = SalesReceipt::with(['customer'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('receipt_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('company_name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->paymentMethod, function ($query) {
                $query->where('payment_method', $this->paymentMethod);
            })
            ->when($this->customer, function ($query) {
                $query->where('customer_id', $this->customer);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('receipt_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('receipt_date', '<=', $this->dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('livewire.sales-receipts.sales-receipts-table', [
            'receipts' => $receipts,
            'customers' => $customers,
        ]);
    }
}
