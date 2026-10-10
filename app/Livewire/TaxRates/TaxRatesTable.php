<?php

namespace App\Livewire\TaxRates;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Item;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Tax rates list (tables plan T4: the shared list design). */
class TaxRatesTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['sort_order', 'name', 'rate'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'sort_order';
            $this->sortDirection = 'asc';
        }
    }

    protected function baseQuery(): Builder
    {
        $query = TaxRate::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'sales' => $query->where('is_active', true)->whereIn('applies_to', [TaxRate::APPLIES_TO_SALES, TaxRate::APPLIES_TO_BOTH]),
            'purchases' => $query->where('is_active', true)->whereIn('applies_to', [TaxRate::APPLIES_TO_PURCHASES, TaxRate::APPLIES_TO_BOTH]),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> */
    private function tabs(): array
    {
        $row = $this->baseQuery()->toBase()->selectRaw(
            "COUNT(*) as all_rows,
             SUM(CASE WHEN is_active = 1 AND applies_to IN ('sales','both') THEN 1 ELSE 0 END) as sales,
             SUM(CASE WHEN is_active = 1 AND applies_to IN ('purchases','both') THEN 1 ELSE 0 END) as purchases,
             SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive"
        )->first();

        $tabs = [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'sales' => ['label' => 'On sales', 'count' => (int) $row->sales, 'alert' => false],
            'purchases' => ['label' => 'On purchases', 'count' => (int) $row->purchases, 'alert' => false],
        ];
        if ((int) $row->inactive > 0 || $this->tab === 'inactive') {
            $tabs['inactive'] = ['label' => 'Inactive', 'count' => (int) $row->inactive, 'alert' => false];
        }

        return $tabs;
    }

    /** Make this the rate new lines start with. */
    public function toggleDefault(int $id): void
    {
        $this->requirePermission('edit tax-rates');
        $taxRate = TaxRate::findOrFail($id);
        if (! $taxRate->is_default) {
            $taxRate->setAsDefault();
        }
        $this->successMessage = "{$taxRate->name} is now the default rate.";
    }

    public function toggleActive(int $id): void
    {
        $this->requirePermission('edit tax-rates');
        $taxRate = TaxRate::findOrFail($id);
        $taxRate->update(['is_active' => ! $taxRate->is_active]);
        $this->successMessage = $taxRate->is_active ? "{$taxRate->name} is active again." : "{$taxRate->name} made inactive.";
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
            $this->errorMessage = 'Tick at least one tax rate first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                TaxRate::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} tax rate(s) made active.";
                break;

            case 'deactivate':
                TaxRate::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} tax rate(s) made inactive.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (TaxRate::whereIn('id', $this->selectedItems)->get() as $taxRate) {
                    if ($this->inUse($taxRate)) {
                        $skipped++;

                        continue;
                    }
                    $taxRate->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} tax rate(s).".($skipped ? " Skipped {$skipped} used in tax groups or on items." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked rate is used in a tax group or on items. Make them inactive instead.';
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
        $taxRate = TaxRate::findOrFail($id);
        if ($this->inUse($taxRate)) {
            $this->errorMessage = "{$taxRate->name} is used in a tax group or on items, so it can't be deleted. Make it inactive instead.";

            return;
        }
        $taxRate->delete();
        $this->successMessage = "Deleted {$taxRate->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function inUse(TaxRate $taxRate): bool
    {
        return $taxRate->taxGroups()->exists() || Item::where('tax_rate_id', $taxRate->id)->exists();
    }

    public function render()
    {
        return view('livewire.tax-rates.tax-rates-table', [
            'taxRates' => $this->rows(),
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
