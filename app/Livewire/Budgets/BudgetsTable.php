<?php

namespace App\Livewire\Budgets;

use App\Models\Budget;
use Livewire\Component;
use Livewire\WithPagination;

class BudgetsTable extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'fiscal_year';
    public $sortDirection = 'desc';
    public $perPage = 15;
    public $yearFilter = '';
    public $statusFilter = '';

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'yearFilter', 'statusFilter'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingYearFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredBudgetIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredBudgetIds());
    }

    private function getFilteredBudgetIds()
    {
        return Budget::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->yearFilter, fn($q) => $q->where('fiscal_year', $this->yearFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one budget.';
            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';
            return;
        }

        $count = count($this->selectedItems);

        switch ($this->bulkAction) {
            case 'activate':
                $activated = 0;
                foreach ($this->selectedItems as $id) {
                    $budget = Budget::find($id);
                    if ($budget && $budget->isDraft() && $budget->lines()->count() > 0) {
                        $budget->activate();
                        $activated++;
                    }
                }
                if ($activated < $count) {
                    $this->errorMessage = "Activated {$activated} of {$count} budgets. Some budgets are already active/locked or have no line items.";
                } else {
                    $this->successMessage = "Successfully activated {$activated} budget(s).";
                }
                break;

            case 'lock':
                $locked = 0;
                foreach ($this->selectedItems as $id) {
                    $budget = Budget::find($id);
                    if ($budget && $budget->isActive()) {
                        $budget->lock();
                        $locked++;
                    }
                }
                if ($locked < $count) {
                    $this->errorMessage = "Locked {$locked} of {$count} budgets. Only active budgets can be locked.";
                } else {
                    $this->successMessage = "Successfully locked {$locked} budget(s).";
                }
                break;

            case 'delete':
                $deleted = 0;
                foreach ($this->selectedItems as $id) {
                    $budget = Budget::find($id);
                    if ($budget && !$budget->isLocked()) {
                        $budget->lines()->delete();
                        $budget->delete();
                        $deleted++;
                    }
                }
                if ($deleted < $count) {
                    $this->errorMessage = "Deleted {$deleted} of {$count} budgets. Locked budgets cannot be deleted.";
                } else {
                    $this->successMessage = "Successfully deleted {$deleted} budget(s).";
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
        $query = Budget::query()
            ->with(['createdBy', 'approvedBy'])
            ->withCount('lines');

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Filters
        if ($this->yearFilter) {
            $query->where('fiscal_year', $this->yearFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        $budgets = $query->paginate($this->perPage);
        
        // Get available years for filter
        $availableYears = Budget::distinct()->pluck('fiscal_year')->sort()->reverse()->values();
        $statuses = Budget::getStatuses();

        return view('livewire.budgets.budgets-table', compact('budgets', 'availableYears', 'statuses'));
    }
}
