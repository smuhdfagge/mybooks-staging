<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

/**
 * What every list does, the same way (tables plan T1): search, status tab,
 * sorting, rows per page, ticked rows and bulk actions.
 *
 * A list component uses this trait with ChecksPermissions, and provides:
 *  - filteredQuery(): the list's query with search and filters applied
 *    (no sorting), so rows, ticked rows, counts and totals all agree;
 *  - sortable(): the columns that may be sorted (anything else is ignored,
 *    so the browser can't ask to sort by an arbitrary column);
 *  - bulkActionPermissions() and applyBulkAction() for its bulk actions.
 * Filter properties listed in $filters reset the page when they change.
 */
trait ListTable
{
    use LimitsPageSize, WithPagination;

    public string $search = '';

    public string $tab = '';

    public string $sortField = '';

    public string $sortDirection = 'desc';

    public $perPage = 25;

    /** @var array<int, string> */
    public array $selectedItems = [];

    public string $bulkAction = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    /** Ticking every row of a large filter is capped here. */
    protected int $maxSelection = 1000;

    abstract protected function filteredQuery(): Builder;

    /** @return array<int, string> */
    abstract protected function sortable(): array;

    /** @return array<int, string> extra filter properties that reset the page */
    protected function filterProperties(): array
    {
        return [];
    }

    /** Livewire runs this on mount: sort by the list's first sortable column. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = $this->sortable()[0];
        }
    }

    public function updating(string $name): void
    {
        if (in_array($name, array_merge(['search', 'tab', 'perPage'], $this->filterProperties()), true)) {
            $this->resetPage();
            $this->selectedItems = [];
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortable(), true)) {
            return;
        }
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->sortField = $field;
    }

    protected function sortColumn(): string
    {
        $sortable = $this->sortable();

        return in_array($this->sortField, $sortable, true) ? $this->sortField : $sortable[0];
    }

    /** @return array<int|string, mixed> relations to load with each page of rows */
    protected function rowRelations(): array
    {
        return [];
    }

    protected function rows(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with($this->rowRelations())
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy($this->filteredQuery()->getModel()->getQualifiedKeyName(), 'desc')
            ->paginate($this->pageSize());
    }

    /** Tick (or untick) every row on the current page. */
    public function selectPage(array $ids, bool $on): void
    {
        $ids = array_map('strval', $ids);
        $this->selectedItems = $on
            ? array_values(array_unique(array_merge($this->selectedItems, $ids)))
            : array_values(array_diff($this->selectedItems, $ids));
    }

    /** Tick every row the search and filters match (up to the cap). */
    public function selectAllMatching(): void
    {
        $this->selectedItems = $this->filteredQuery()->limit($this->maxSelection)
            ->pluck($this->filteredQuery()->getModel()->getQualifiedKeyName())
            ->map(fn ($id) => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function clearFilters(): void
    {
        $this->reset(array_merge(['search', 'tab'], $this->filterProperties()));
        $this->resetPage();
    }

    /** Run a bulk action from the bulk bar on the ticked rows. */
    public function runBulk(string $action): void
    {
        $this->bulkAction = $action;
        $this->authorizeBulkAction();
        $this->applyBulkAction();
    }
}
