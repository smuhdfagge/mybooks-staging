<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $status = '';

    public $sortField = 'name';

    public $sortDirection = 'asc';

    public $perPage = 10;

    public $successMessage = '';

    public $errorMessage = '';

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredCustomerIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredCustomerIds());
    }

    private function getFilteredCustomerIds()
    {
        return Customer::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
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
            'activate' => 'edit customers',
            'deactivate' => 'edit customers',
            'delete' => 'delete customers',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one customer.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Customer::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} customer(s).";
                break;

            case 'deactivate':
                Customer::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} customer(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $customerId) {
                    $customer = Customer::find($customerId);
                    if (! $customer) {
                        continue;
                    }

                    // Check if customer has related records
                    if ($customer->invoices()->exists() ||
                        $customer->salesOrders()->exists() ||
                        $customer->salesReceipts()->exists() ||
                        $customer->payments()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $customer->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} customer(s). Skipped {$skippedCount} customer(s) with existing records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} customer(s).";
                } else {
                    $this->errorMessage = 'Could not delete any customers. All selected customers have existing records.';
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

    public function deleteCustomer($customerId)
    {
        $this->requirePermission('delete customers');

        $this->successMessage = '';
        $this->errorMessage = '';

        $customer = Customer::findOrFail($customerId);

        // Check if customer has related records that prevent deletion
        if ($customer->invoices()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing invoices.';

            return;
        }

        if ($customer->salesOrders()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing sales orders.';

            return;
        }

        if ($customer->salesReceipts()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing sales receipts.';

            return;
        }

        if ($customer->payments()->exists()) {
            $this->errorMessage = 'Cannot delete customer with existing payments.';

            return;
        }

        $customer->delete();

        $this->successMessage = 'Customer deleted successfully.';
    }

    public function render()
    {
        $customers = Customer::query()
            ->withBalances()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.customers.customers-table', [
            'customers' => $customers,
        ]);
    }
}
