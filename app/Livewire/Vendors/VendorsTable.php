<?php

namespace App\Livewire\Vendors;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\Vendor;
use Livewire\Component;
use Livewire\WithPagination;

class VendorsTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $sortField = 'company_name';

    public $sortDirection = 'asc';

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
            $this->selectedItems = $this->getFilteredVendorIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredVendorIds());
    }

    private function getFilteredVendorIds()
    {
        return Vendor::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('contact_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
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
            'activate' => 'edit vendors',
            'deactivate' => 'edit vendors',
            'delete' => 'delete vendors',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one vendor.';

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
                Vendor::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} vendor(s).";
                break;

            case 'deactivate':
                Vendor::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} vendor(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $vendorId) {
                    $vendor = Vendor::find($vendorId);
                    if (! $vendor) {
                        continue;
                    }

                    // Check if vendor has related records
                    if ($vendor->bills()->exists() ||
                        $vendor->expenses()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $vendor->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} vendor(s). Skipped {$skippedCount} vendor(s) with existing records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} vendor(s).";
                } else {
                    $this->errorMessage = 'Could not delete any vendors. All selected vendors have existing records.';
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
        $vendors = Vendor::query()
            ->withBalances()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('contact_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        return view('livewire.vendors.vendors-table', [
            'vendors' => $vendors,
        ]);
    }
}
