<?php

namespace App\Livewire\Deductions;

use App\Models\Deduction;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\ChecksPermissions;

class DeductionsTable extends Component
{
    use ChecksPermissions, WithPagination;

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

    public function toggleActive(Deduction $deduction)
    {
        $this->requirePermission('create payroll');

        abort_unless($deduction->tenant_id === auth()->user()->tenant_id, 403);

        $deduction->update(['is_active' => !$deduction->is_active]);
        $this->successMessage = 'Deduction status updated.';
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
            $this->errorMessage = 'Please select at least one deduction.';
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
                Deduction::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} deduction(s).";
                break;

            case 'deactivate':
                Deduction::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} deduction(s).";
                break;

            case 'delete':
                Deduction::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->delete();
                $this->successMessage = "Successfully deleted {$count} deduction(s).";
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
        $query = Deduction::query()->latest();

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
        $deductions = $this->buildQuery()->paginate($this->perPage);

        return view('livewire.deductions.deductions-table', compact('deductions'));
    }
}
