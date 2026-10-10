<?php

namespace App\Livewire\Vendors;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Bill;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Vendors list (tables plan T3: the shared list design, see InvoicesTable).
 */
class VendorsTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
    ];

    protected function sortable(): array
    {
        return ['name', 'outstanding_balance', 'total_purchases', 'created_at'];
    }

    protected function baseQuery(): Builder
    {
        $query = Vendor::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        return $query;
    }

    /** Tabs: active, owed (unpaid bills), inactive. */
    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'owed' => $query->whereHas('bills', fn ($b) => $b->whereNotIn('status', Vendor::NOT_OWED_STATUSES)->where('balance_due', '>', 0)),
            default => $query,
        };
    }

    /** Vendors sort by name A–Z first. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw('COUNT(*) as all_rows, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();
        $owed = $this->applyTab($this->baseQuery(), 'owed')->count();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'active' => ['label' => 'Active', 'count' => (int) $row->active, 'alert' => false],
            'owed' => ['label' => 'You owe', 'count' => $owed, 'alert' => false],
            'inactive' => ['label' => 'Inactive', 'count' => (int) $row->all_rows - (int) $row->active, 'alert' => false],
        ];
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit vendors',
            'deactivate' => 'edit vendors',
            'delete' => 'delete vendors',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one vendor first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Vendor::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} vendor(s) made active.";
                break;

            case 'deactivate':
                Vendor::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} vendor(s) made inactive.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (Vendor::whereIn('id', $this->selectedItems)->get() as $vendor) {
                    if ($this->hasRecords($vendor)) {
                        $skipped++;

                        continue;
                    }
                    $vendor->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} vendor(s).".($skipped ? " Skipped {$skipped} with bills or expenses." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked vendor has bills or expenses.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one vendor from its row menu (vendors with records are kept). */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete vendors');
        $vendor = Vendor::findOrFail($id);
        if ($this->hasRecords($vendor)) {
            $this->errorMessage = "{$vendor->name} can't be deleted: it has bills or expenses. Make it inactive instead.";

            return;
        }
        $vendor->delete();
        $this->successMessage = "Deleted {$vendor->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function hasRecords(Vendor $vendor): bool
    {
        return $vendor->bills()->exists() || $vendor->expenses()->exists();
    }

    public function render()
    {
        // Balances in the same query (no query per row).
        $vendors = $this->filteredQuery()->scopes(['withBalances'])
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('vendors.id')
            ->paginate($this->pageSize());

        $owed = Bill::query()
            ->whereIn('vendor_id', $this->filteredQuery()->select('vendors.id'))
            ->whereNotIn('status', Vendor::NOT_OWED_STATUSES)
            ->sum('balance_due');

        return view('livewire.vendors.vendors-table', [
            'vendors' => $vendors,
            'tabs' => $this->tabs(),
            'totals' => (object) ['n' => $vendors->total(), 'owed' => (float) $owed],
            'filtered' => $this->isFiltered(),
        ]);
    }
}
