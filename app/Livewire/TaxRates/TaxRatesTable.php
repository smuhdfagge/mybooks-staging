<?php

namespace App\Livewire\TaxRates;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\TaxRate;
use Livewire\Component;
use Livewire\WithPagination;

class TaxRatesTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $appliesTo = '';

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
        'appliesTo' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingAppliesTo()
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
        $this->reset(['search', 'appliesTo', 'showInactive']);
        $this->resetPage();
    }

    public function toggleDefault(TaxRate $taxRate)
    {
        $this->requirePermission('edit tax-rates');

        if (! $taxRate->is_default) {
            $taxRate->setAsDefault();
            session()->flash('message', 'Default tax rate updated.');
        }
    }

    public function toggleActive(TaxRate $taxRate)
    {
        $this->requirePermission('edit tax-rates');

        $taxRate->update(['is_active' => ! $taxRate->is_active]);
        session()->flash('message', 'Tax rate status updated.');
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredTaxRateIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredTaxRateIds());
    }

    private function getFilteredTaxRateIds()
    {
        $query = TaxRate::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if ($this->appliesTo) {
            $query->where('applies_to', $this->appliesTo);
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
            $this->errorMessage = 'Please select at least one tax rate.';

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
                TaxRate::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} tax rate(s).";
                break;

            case 'deactivate':
                TaxRate::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} tax rate(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $taxRateId) {
                    $taxRate = TaxRate::find($taxRateId);
                    if (! $taxRate) {
                        continue;
                    }

                    // Check if tax rate is used in tax groups or transactions
                    if ($taxRate->taxGroups()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $taxRate->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} tax rate(s). Skipped {$skippedCount} tax rate(s) used in tax groups.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} tax rate(s).";
                } else {
                    $this->errorMessage = 'Could not delete any tax rates. All selected tax rates are used in tax groups.';
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
        $query = TaxRate::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if ($this->appliesTo) {
            $query->where('applies_to', $this->appliesTo);
        }

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        $taxRates = $query->paginate($this->perPage);

        return view('livewire.tax-rates.tax-rates-table', compact('taxRates'));
    }
}
