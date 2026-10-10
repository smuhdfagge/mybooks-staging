<?php

namespace App\Livewire\Budgets;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Budgets list (tables plan T4: the shared list design). */
class BudgetsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $year = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'year' => ['except' => ''],
    ];

    public const LABELS = ['draft' => 'Draft', 'active' => 'In use', 'locked' => 'Locked'];

    protected function sortable(): array
    {
        return ['fiscal_year', 'name', 'lines_sum_annual_total'];
    }

    protected function filterProperties(): array
    {
        return ['year'];
    }

    protected function decorateRows(Builder $query): Builder
    {
        return $query->withCount('lines')->withSum('lines', 'annual_total');
    }

    protected function baseQuery(): Builder
    {
        $query = Budget::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }
        if ($this->year !== '' && ctype_digit($this->year)) {
            $query->where('fiscal_year', (int) $this->year);
        }

        return $query;
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit budgets',
            'lock' => 'edit budgets',
            'delete' => 'delete budgets',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one budget first.';

            return;
        }

        $this->authorizeBulkAction();
        $budgets = Budget::withCount('lines')->whereIn('id', $this->selectedItems)->get();

        switch ($this->bulkAction) {
            case 'activate':
                $n = 0;
                foreach ($budgets as $budget) {
                    if ($budget->isDraft() && $budget->lines_count > 0) {
                        $budget->activate();
                        $n++;
                    }
                }
                $this->successMessage = "Put {$n} budget(s) in use. Only drafts with lines change.";
                break;

            case 'lock':
                $n = 0;
                foreach ($budgets as $budget) {
                    if ($budget->isActive()) {
                        $budget->lock();
                        $n++;
                    }
                }
                $this->successMessage = "Locked {$n} budget(s). Only budgets in use can be locked.";
                break;

            case 'delete':
                $n = 0;
                foreach ($budgets as $budget) {
                    if (! $budget->isLocked()) {
                        $this->deleteBudget($budget);
                        $n++;
                    }
                }
                $this->successMessage = "Deleted {$n} budget(s). Locked budgets are kept.";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one budget from its row menu (locked budgets are kept). */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete budgets');
        $budget = Budget::findOrFail($id);
        if ($budget->isLocked()) {
            $this->errorMessage = "{$budget->name} is locked, so it can't be deleted.";

            return;
        }
        $this->deleteBudget($budget);
        $this->successMessage = "Deleted {$budget->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function deleteBudget(Budget $budget): void
    {
        DB::transaction(function () use ($budget) {
            $budget->lines()->delete();
            $budget->delete();
        });
    }

    public function render()
    {
        return view('livewire.budgets.budgets-table', [
            'budgets' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS),
            'years' => Budget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->mapWithKeys(fn ($y) => [(string) $y => (string) $y]),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
