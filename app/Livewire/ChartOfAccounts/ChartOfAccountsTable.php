<?php

namespace App\Livewire\ChartOfAccounts;

use App\Models\ChartOfAccount;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\ChecksPermissions;

class ChartOfAccountsTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';
    public $sortField = 'account_code';
    public $sortDirection = 'asc';
    public $perPage = 25;
    public $typeFilter = '';
    public $viewMode = 'tree'; // 'flat' or 'tree'
    public $collapsedTypes = [];

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'typeFilter', 'viewMode'];

    public function toggleType($type)
    {
        if (in_array($type, $this->collapsedTypes)) {
            $this->collapsedTypes = array_values(array_diff($this->collapsedTypes, [$type]));
        } else {
            $this->collapsedTypes[] = $type;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
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
            $this->selectedItems = $this->getFilteredAccountIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredAccountIds());
    }

    private function getFilteredAccountIds()
    {
        return ChartOfAccount::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('account_code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit chart-of-accounts',
            'deactivate' => 'edit chart-of-accounts',
            'delete' => 'delete chart-of-accounts',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one account.';
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
                ChartOfAccount::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} account(s).";
                break;

            case 'deactivate':
                ChartOfAccount::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} account(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;
                
                foreach ($this->selectedItems as $accountId) {
                    $account = ChartOfAccount::find($accountId);
                    if (!$account) continue;
                    
                    // Check if account has related records
                    if ($account->journalEntries()->exists() || 
                        $account->children()->exists() ||
                        $account->is_system) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $account->delete();
                    $deletedCount++;
                }
                
                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} account(s). Skipped {$skippedCount} account(s) with existing records or system accounts.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} account(s).";
                } else {
                    $this->errorMessage = "Could not delete any accounts. Selected accounts have existing records or are system accounts.";
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
        $accounts = ChartOfAccount::with(['parent'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('account_code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $types = ChartOfAccount::getTypes();

        // Group accounts by type for tree view
        $groupedAccounts = [];
        if ($this->viewMode === 'tree') {
            $allAccounts = ChartOfAccount::with(['parent', 'children'])
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('account_code', 'like', '%' . $this->search . '%')
                          ->orWhere('name', 'like', '%' . $this->search . '%')
                          ->orWhere('description', 'like', '%' . $this->search . '%');
                    });
                })
                ->when($this->typeFilter, function ($query) {
                    $query->where('type', $this->typeFilter);
                })
                ->orderBy('account_code', 'asc')
                ->get();

            $groupedAccounts = $allAccounts->groupBy('type');
        }

        return view('livewire.chart-of-accounts.chart-of-accounts-table', [
            'accounts' => $accounts,
            'types' => $types,
            'groupedAccounts' => $groupedAccounts,
        ]);
    }
}
