<?php

namespace App\Livewire\TaxGroups;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Item;
use App\Models\TaxGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Tax groups list (tables plan T4): several rates charged together. */
class TaxGroupsTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['name', 'code'];
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
        return ['taxRates'];
    }

    protected function baseQuery(): Builder
    {
        $query = TaxGroup::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
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
        $this->requirePermission('edit tax-rates');
        $taxGroup = TaxGroup::findOrFail($id);
        $taxGroup->update(['is_active' => ! $taxGroup->is_active]);
        $this->successMessage = $taxGroup->is_active ? "{$taxGroup->name} is active again." : "{$taxGroup->name} made inactive.";
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit tax-rates',
            'deactivate' => 'edit tax-rates',
            'delete' => 'delete tax-rates',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one tax group first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                TaxGroup::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} tax group(s) made active.";
                break;

            case 'deactivate':
                TaxGroup::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} tax group(s) made inactive.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (TaxGroup::whereIn('id', $this->selectedItems)->get() as $taxGroup) {
                    if ($this->inUse($taxGroup)) {
                        $skipped++;

                        continue;
                    }
                    $this->deleteGroup($taxGroup);
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} tax group(s).".($skipped ? " Skipped {$skipped} used on items." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked group is used on items. Make them inactive instead.';
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
        $this->requirePermission('delete tax-rates');
        $taxGroup = TaxGroup::findOrFail($id);
        if ($this->inUse($taxGroup)) {
            $this->errorMessage = "{$taxGroup->name} is used on items, so it can't be deleted. Make it inactive instead.";

            return;
        }
        $this->deleteGroup($taxGroup);
        $this->successMessage = "Deleted {$taxGroup->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function inUse(TaxGroup $taxGroup): bool
    {
        return Item::where('tax_group_id', $taxGroup->id)->exists();
    }

    private function deleteGroup(TaxGroup $taxGroup): void
    {
        DB::transaction(function () use ($taxGroup) {
            $taxGroup->taxRates()->detach();
            $taxGroup->delete();
        });
    }

    public function render()
    {
        return view('livewire.tax-groups.tax-groups-table', [
            'taxGroups' => $this->rows(),
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
