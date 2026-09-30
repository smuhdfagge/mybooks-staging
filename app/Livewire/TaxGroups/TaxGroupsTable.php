<?php

namespace App\Livewire\TaxGroups;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\TaxGroup;
use Livewire\Component;
use Livewire\WithPagination;

class TaxGroupsTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $showInactive = false;

    public $perPage = 15;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingShowInactive()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'showInactive']);
        $this->resetPage();
    }

    public function toggleActive(TaxGroup $taxGroup)
    {
        $this->requirePermission('edit tax-rates');

        $taxGroup->update(['is_active' => ! $taxGroup->is_active]);
        session()->flash('message', 'Tax group status updated.');
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredTaxGroupIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredTaxGroupIds());
    }

    private function getFilteredTaxGroupIds()
    {
        $query = TaxGroup::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        return $query->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit tax-rates',
            'deactivate' => 'edit tax-rates',
            'delete' => 'delete tax-rates',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one tax group.';

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
                TaxGroup::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} tax group(s).";
                break;

            case 'deactivate':
                TaxGroup::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} tax group(s).";
                break;

            case 'delete':
                $deletedCount = 0;

                foreach ($this->selectedItems as $taxGroupId) {
                    $taxGroup = TaxGroup::find($taxGroupId);
                    if (! $taxGroup) {
                        continue;
                    }

                    $taxGroup->taxRates()->detach();
                    $taxGroup->delete();
                    $deletedCount++;
                }

                $this->successMessage = "Successfully deleted {$deletedCount} tax group(s).";
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
        $query = TaxGroup::with('taxRates')
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        $taxGroups = $query->paginate($this->pageSize());

        return view('livewire.tax-groups.tax-groups-table', compact('taxGroups'));
    }
}
