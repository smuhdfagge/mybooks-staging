<?php

namespace App\Livewire\RecurrentExpenses;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Livewire\RecurrentBills\RecurrentBillsTable;
use App\Models\ChartOfAccount;
use App\Models\RecurrentExpense;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Recurrent expenses list (tables plan T3): expenses MyBooks records on a schedule.
 */
class RecurrentExpensesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $account = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'account' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['next_expense_date', 'profile_name', 'total'];
    }

    /** Soonest next expense first. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'next_expense_date';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['vendor:id,name', 'expenseAccount:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['account'];
    }

    protected function baseQuery(): Builder
    {
        $query = RecurrentExpense::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('profile_name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%")));
        }
        if ($this->account !== '' && ctype_digit($this->account)) {
            $query->where('expense_account_id', (int) $this->account);
        }

        return $query;
    }

    /** Pause an active profile, or start a paused one again. */
    public function toggleOne(int $id): void
    {
        $this->requirePermission('edit recurrent-expenses');
        $profile = RecurrentExpense::findOrFail($id);
        if ($profile->status === 'stopped') {
            $this->errorMessage = "{$profile->profile_name} has stopped. Edit it to set a new end date.";

            return;
        }
        $profile->update(['status' => $profile->status === 'active' ? 'paused' : 'active']);
        $this->successMessage = $profile->status === 'active' ? "{$profile->profile_name} started again." : "{$profile->profile_name} paused.";
    }

    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete recurrent-expenses');
        $profile = RecurrentExpense::findOrFail($id);
        $profile->delete();
        $this->successMessage = "Deleted {$profile->profile_name}. Expenses it already recorded are kept.";
    }

    public function render()
    {
        return view('livewire.recurrent-expenses.recurrent-expenses-table', [
            'profiles' => $this->rows(),
            'tabs' => $this->statusTabs(RecurrentBillsTable::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(CASE WHEN status = \'active\' THEN '.RecurrentBillsTable::PER_MONTH.' ELSE 0 END), 0) as per_month')->first(),
            'accounts' => ChartOfAccount::whereIn('id', RecurrentExpense::query()->select('expense_account_id')->distinct())->orderBy('name')->pluck('name', 'id'),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
