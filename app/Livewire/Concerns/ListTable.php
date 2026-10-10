<?php

namespace App\Livewire\Concerns;

use App\Services\Dashboard\DashboardService;
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

    /** The list's query with search and filters applied, but not the status tab. */
    abstract protected function baseQuery(): Builder;

    /** The list's query with the status tab too: rows, ticked rows and totals use it. */
    protected function filteredQuery(): Builder
    {
        return $this->applyTab($this->baseQuery(), $this->tab);
    }

    /** By default a tab is a value of the status column. */
    protected function applyTab(Builder $query, string $tab): Builder
    {
        return $tab === '' ? $query : $query->where($query->getModel()->qualifyColumn('status'), $tab);
    }

    /**
     * Status tabs with counts from one grouped query on the status column.
     *
     * @param  array<string, string>  $labels  status => label, in tab order
     * @param  list<string>  $alert  statuses whose count shows in red
     * @return array<string, array{label: string, count: int, alert: bool}>
     */
    protected function statusTabs(array $labels, array $alert = [], string $column = 'status', bool $hideEmpty = false): array
    {
        $counts = $this->baseQuery()->toBase()->reorder()
            ->selectRaw("{$column} as tab_key, COUNT(*) as n")->groupBy($column)
            ->pluck('n', 'tab_key')->map(fn ($n) => (int) $n);

        $tabs = ['' => ['label' => 'All', 'count' => (int) $counts->sum(), 'alert' => false]];
        foreach ($labels as $key => $label) {
            $n = (int) ($counts[$key] ?? 0);
            if ($hideEmpty && $n === 0 && $this->tab !== $key) {
                continue;
            }
            $tabs[$key] = ['label' => $label, 'count' => $n, 'alert' => in_array($key, $alert, true)];
        }

        return $tabs;
    }

    /** Whether any search or filter (not the tab) is on. */
    protected function isFiltered(): bool
    {
        foreach (array_merge(['search'], $this->filterProperties()) as $p) {
            if (trim((string) $this->{$p}) !== '') {
                return true;
            }
        }

        return false;
    }

    /** "Date: This month…" filter on $column, using the business's financial year. */
    protected function applyPeriod(Builder $query, string $column, string $period): Builder
    {
        if ($period === '' || ! array_key_exists($period, self::periodOptions())) {
            return $query;
        }
        $p = app(DashboardService::class)->period((int) auth()->user()->tenant_id, $period, 'none');

        return $query->where($column, '>=', $p->from->toDateString())
            ->where($column, '<', $p->to->copy()->addDay()->toDateString());
    }

    /** @return array<string, string> */
    public static function periodOptions(): array
    {
        return [
            '' => 'All time',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'this_year' => 'This financial year',
            'last_12_months' => 'Last 12 months',
        ];
    }

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

    /** Extra columns for the rows only (counts, sums, sub-selects); not used for tabs or totals. */
    protected function decorateRows(Builder $query): Builder
    {
        return $query;
    }

    protected function rows(): LengthAwarePaginator
    {
        return $this->decorateRows($this->filteredQuery())
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
        // Only lists with a bulk bar have applyBulkAction(); elsewhere refuse.
        abort_unless(method_exists($this, 'applyBulkAction'), 403);
        $this->bulkAction = $action;
        $this->authorizeBulkAction();
        call_user_func([$this, 'applyBulkAction']);
    }
}
