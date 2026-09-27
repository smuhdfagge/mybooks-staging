<?php

namespace App\Livewire\Allowances;

use App\Models\Allowance;
use Livewire\Component;
use Livewire\WithPagination;

class AllowancesTable extends Component
{
    use WithPagination;

    public $search = '';
    public $amountType = '';
    public $showInactive = false;
    public $perPage = 15;

    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'amountType' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingAmountType() { $this->resetPage(); }
    public function updatingShowInactive() { $this->resetPage(); }
    public function updatingPerPage() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'amountType', 'showInactive']);
        $this->resetPage();
    }

    public function toggleActive(Allowance $allowance)
    {
        abort_unless($allowance->tenant_id === auth()->user()->tenant_id, 403);

        $allowance->update(['is_active' => !$allowance->is_active]);
        $this->successMessage = 'Allowance status updated.';
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
        return $this->buildQuery()->pluck('id')->map(fn($id) => (string) $id)->toArray();
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one allowance.';
            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';
            return;
        }

        $count = count($this->selectedItems);

        switch ($this->bulkAction) {
            case 'activate':
                Allowance::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} allowance(s).";
                break;

            case 'deactivate':
                Allowance::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} allowance(s).";
                break;

            case 'delete':
                Allowance::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->delete();
                $this->successMessage = "Successfully deleted {$count} allowance(s).";
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
        $query = Allowance::query()->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->amountType) {
            $query->where('amount_type', $this->amountType);
        }

        if (!$this->showInactive) {
            $query->where('is_active', true);
        }

        return $query;
    }

    public function render()
    {
        $allowances = $this->buildQuery()->paginate($this->perPage);

        return view('livewire.allowances.allowances-table', compact('allowances'));
    }
}
