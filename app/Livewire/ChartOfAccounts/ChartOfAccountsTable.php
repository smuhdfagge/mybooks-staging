<?php

namespace App\Livewire\ChartOfAccounts;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Chart of accounts (tables plan T4): one tab per account type, in code
 * order, sub-accounts shown under their parent.
 */
class ChartOfAccountsTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'type'],
    ];

    /** Type => tab label, in the order accounts are usually read. */
    public const LABELS = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'];

    protected function sortable(): array
    {
        return ['account_code', 'name', 'current_balance'];
    }

    /** Accounts read in code order. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'account_code';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['parent:id,account_code,name'];
    }

    protected function baseQuery(): Builder
    {
        $query = ChartOfAccount::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('account_code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match (true) {
            array_key_exists($tab, self::LABELS) => $query->where('type', $tab),
            $tab === 'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one grouped query */
    private function tabs(): array
    {
        $counts = $this->baseQuery()->toBase()->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $inactive = $this->baseQuery()->where('is_active', false)->count();

        $tabs = ['' => ['label' => 'All', 'count' => (int) $counts->sum(), 'alert' => false]];
        foreach (self::LABELS as $key => $label) {
            $tabs[$key] = ['label' => $label, 'count' => (int) ($counts[$key] ?? 0), 'alert' => false];
        }
        if ($inactive > 0 || $this->tab === 'inactive') {
            $tabs['inactive'] = ['label' => 'Inactive', 'count' => $inactive, 'alert' => false];
        }

        return $tabs;
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
            $this->errorMessage = 'Tick at least one account first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                ChartOfAccount::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} account(s) made active.";
                break;

            case 'deactivate':
                ChartOfAccount::whereIn('id', $this->selectedItems)->where('is_system', false)->update(['is_active' => false]);
                $this->successMessage = 'Accounts made inactive. Accounts MyBooks needs stay active.';
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (ChartOfAccount::whereIn('id', $this->selectedItems)->get() as $account) {
                    if ($this->blockedBecause($account)) {
                        $skipped++;

                        continue;
                    }
                    $account->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} account(s).".($skipped ? " Skipped {$skipped} that are in use or that MyBooks needs." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked account is in use or is one MyBooks needs.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one unused account from its row menu. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete chart-of-accounts');
        $account = ChartOfAccount::findOrFail($id);
        if ($reason = $this->blockedBecause($account)) {
            $this->errorMessage = $reason;

            return;
        }
        $account->delete();
        $this->successMessage = "Deleted {$account->account_code} {$account->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function blockedBecause(ChartOfAccount $account): ?string
    {
        return match (true) {
            (bool) $account->is_system => "{$account->name} is an account MyBooks needs, so it can't be deleted.",
            $account->journalEntries()->exists() => "{$account->name} has entries, so it can't be deleted. Make it inactive instead.",
            $account->children()->exists() => "{$account->name} has sub-accounts. Move or delete those first.",
            default => null,
        };
    }

    public function render()
    {
        $balances = null;
        if (array_key_exists($this->tab, self::LABELS)) {
            $balances = (float) $this->filteredQuery()->sum('current_balance');
        }

        return view('livewire.chart-of-accounts.chart-of-accounts-table', [
            'accounts' => $this->rows(),
            'tabs' => $this->tabs(),
            'types' => ChartOfAccount::getTypes(),
            'total' => $balances,
            'filtered' => $this->isFiltered(),
        ]);
    }
}
