<?php

namespace App\Livewire\Banks;

use App\Models\Bank;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\ChecksPermissions;

class BanksTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 15;
    public $typeFilter = '';
    public $statusFilter = '';

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = ['search', 'sortField', 'sortDirection', 'typeFilter', 'statusFilter'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
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
            $this->selectedItems = $this->getFilteredBankIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredBankIds());
    }

    private function getFilteredBankIds()
    {
        return Bank::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('bank_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, fn($q) => $q->where('account_type', $this->typeFilter))
            ->when($this->statusFilter !== '', function ($q) {
                $q->where('is_active', $this->statusFilter === 'active');
            })
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
            'activate' => 'edit banks',
            'deactivate' => 'edit banks',
            'delete' => 'delete banks',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one bank account.';
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
                Bank::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} bank account(s).";
                break;

            case 'deactivate':
                Bank::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} bank account(s).";
                break;

            case 'delete':
                $deleted = 0;
                foreach ($this->selectedItems as $id) {
                    $bank = Bank::find($id);
                    if ($bank && $bank->transactions()->count() === 0) {
                        $bank->delete();
                        $deleted++;
                    }
                }
                if ($deleted < $count) {
                    $this->errorMessage = "Deleted {$deleted} of {$count} bank accounts. Some accounts have transactions and cannot be deleted.";
                } else {
                    $this->successMessage = "Successfully deleted {$deleted} bank account(s).";
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
        $query = Bank::query()
            ->with('chartOfAccount');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('bank_name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->typeFilter) {
            $query->where('account_type', $this->typeFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        // Calculate totals
        $totals = [
            'total_balance' => Bank::where('is_active', true)->sum('current_balance'),
            'active_accounts' => Bank::where('is_active', true)->count(),
        ];

        return view('livewire.banks.banks-table', [
            'banks' => $query->paginate($this->perPage),
            'accountTypes' => Bank::getAccountTypes(),
            'totals' => $totals,
        ]);
    }
}
