<?php

namespace App\Livewire\SalaryStructures;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\SalaryStructure;
use Livewire\Component;
use Livewire\WithPagination;

class SalaryStructuresTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $perPage = 15;

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

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function toggleActive(SalaryStructure $salaryStructure)
    {
        $this->requirePermission('create payroll');

        abort_unless($salaryStructure->tenant_id === auth()->user()->tenant_id, 403);

        $salaryStructure->update(['is_active' => ! $salaryStructure->is_active]);
        $this->successMessage = 'Salary structure status updated.';
    }

    public function updatedSelectAll($value)
    {
        $this->selectedItems = $value ? $this->getFilteredIds() : [];
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredIds());
    }

    private function getFilteredIds()
    {
        return $this->buildQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'create payroll',
            'deactivate' => 'create payroll',
            'delete' => 'create payroll',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one salary structure.';

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
                SalaryStructure::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} salary structure(s).";
                break;

            case 'deactivate':
                SalaryStructure::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} salary structure(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $id) {
                    $structure = SalaryStructure::find($id);
                    if (! $structure) {
                        continue;
                    }

                    if ($structure->payrolls()->exists() || $structure->employees()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $structure->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} structure(s). Skipped {$skippedCount} in use.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} salary structure(s).";
                } else {
                    $this->errorMessage = 'Could not delete any structures. All selected are in use.';
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

    private function buildQuery()
    {
        $query = SalaryStructure::with(['items'])->latest();

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if ($this->status === 'active') {
            $query->where('is_active', true);
        } elseif ($this->status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    public function render()
    {
        $salaryStructures = $this->buildQuery()->paginate($this->pageSize());

        return view('livewire.salary-structures.salary-structures-table', compact('salaryStructures'));
    }
}
