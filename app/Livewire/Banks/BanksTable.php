<?php

namespace App\Livewire\Banks;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Bank and cash accounts list (tables plan T4: the shared list design). */
class BanksTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $type = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
        'type' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['name', 'current_balance'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function filterProperties(): array
    {
        return ['type'];
    }

    protected function baseQuery(): Builder
    {
        $query = Bank::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('bank_name', 'like', "%{$term}%"));
        }
        if (array_key_exists($this->type, Bank::getAccountTypes())) {
            $query->where('account_type', $this->type);
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
            $this->errorMessage = 'Tick at least one account first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Bank::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} account(s) back in use.";
                break;

            case 'deactivate':
                Bank::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} account(s) taken out of use.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (Bank::whereIn('id', $this->selectedItems)->get() as $bank) {
                    if ($bank->deleteBlockedReason()) {
                        $skipped++;

                        continue;
                    }
                    $bank->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} account(s).".($skipped ? " Skipped {$skipped} with money recorded through them." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked account has money recorded through it. Take them out of use instead.';
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
        $this->requirePermission('delete banks');
        $bank = Bank::findOrFail($id);
        if ($reason = $bank->deleteBlockedReason()) {
            $this->errorMessage = $reason;

            return;
        }
        $bank->delete();
        $this->successMessage = "Deleted {$bank->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.banks.banks-table', [
            'banks' => $this->rows(),
            'tabs' => $this->tabs(),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(CASE WHEN is_active = 1 THEN current_balance ELSE 0 END), 0) as balance')->first(),
            'types' => Bank::getAccountTypes(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
