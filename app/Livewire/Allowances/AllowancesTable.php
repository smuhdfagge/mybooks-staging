<?php

namespace App\Livewire\Allowances;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Allowance;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Allowances that can go into salary structures (tables plan T5). */
class AllowancesTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['name', 'amount'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function baseQuery(): Builder
    {
        $query = Allowance::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw('COUNT(*) as all_rows, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'active' => ['label' => 'Active', 'count' => (int) $row->active, 'alert' => false],
            'inactive' => ['label' => 'Inactive', 'count' => (int) $row->all_rows - (int) $row->active, 'alert' => false],
        ];
    }

    public function toggleActive(int $id): void
    {
        $this->requirePermission('create payroll');
        $row = Allowance::findOrFail($id);
        $row->update(['is_active' => ! $row->is_active]);
        $this->successMessage = $row->is_active ? "{$row->name} is active again." : "{$row->name} made inactive.";
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
            $this->errorMessage = 'Tick at least one allowance first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
            case 'deactivate':
                Allowance::whereIn('id', $this->selectedItems)->update(['is_active' => $this->bulkAction === 'activate']);
                $this->successMessage = "{$count} allowance(s) made ".($this->bulkAction === 'activate' ? 'active.' : 'inactive.');
                break;

            case 'delete':
                // Salary structures keep their own copy of each line, so deleting here changes no one's pay.
                Allowance::whereIn('id', $this->selectedItems)->delete();
                $this->successMessage = "Deleted {$count} allowance(s).";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function deleteOne(int $id): void
    {
        $this->requirePermission('create payroll');
        $row = Allowance::findOrFail($id);
        $row->delete();
        $this->successMessage = "Deleted {$row->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.allowances.allowances-table', [
            'rows' => $this->rows(),
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
