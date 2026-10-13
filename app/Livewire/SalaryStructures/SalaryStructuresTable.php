<?php

namespace App\Livewire\SalaryStructures;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Salary structures (tables plan T5): basic pay plus allowances and deductions. */
class SalaryStructuresTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['name', 'basic_salary', 'effective_from', 'employees_count'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['allowances', 'deductions'];
    }

    protected function decorateRows(Builder $query): Builder
    {
        return $query->withCount('employees');
    }

    protected function baseQuery(): Builder
    {
        $query = SalaryStructure::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%"));
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
            'active' => ['label' => 'In use', 'count' => (int) $row->active, 'alert' => false],
            'inactive' => ['label' => 'Not in use', 'count' => (int) $row->all_rows - (int) $row->active, 'alert' => false],
        ];
    }

    public function toggleActive(int $id): void
    {
        $this->requirePermission('create payroll');
        $structure = SalaryStructure::findOrFail($id);
        $structure->update(['is_active' => ! $structure->is_active]);
        $this->successMessage = $structure->is_active ? "{$structure->name} is in use again." : "{$structure->name} taken out of use.";
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
            $this->errorMessage = 'Tick at least one structure first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
            case 'deactivate':
                SalaryStructure::whereIn('id', $this->selectedItems)->update(['is_active' => $this->bulkAction === 'activate']);
                $this->successMessage = $this->bulkAction === 'activate' ? "{$count} structure(s) put in use." : "{$count} structure(s) taken out of use.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (SalaryStructure::whereIn('id', $this->selectedItems)->get() as $structure) {
                    if ($this->inUse($structure)) {
                        $skipped++;

                        continue;
                    }
                    $structure->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} structure(s).".($skipped ? " Skipped {$skipped} that employees or payroll runs use." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked structure is used by employees or payroll runs.';
                }
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
        $structure = SalaryStructure::findOrFail($id);
        if ($this->inUse($structure)) {
            $this->errorMessage = "{$structure->name} is used by employees or payroll runs, so it can't be deleted. Take it out of use instead.";

            return;
        }
        $structure->delete();
        $this->successMessage = "Deleted {$structure->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function inUse(SalaryStructure $structure): bool
    {
        return $structure->payrolls()->exists() || $structure->employees()->exists();
    }

    public function render()
    {
        return view('livewire.salary-structures.salary-structures-table', [
            'structures' => $this->rows(),
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
