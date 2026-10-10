<?php

namespace App\Livewire\Items;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Item categories list (tables plan T4: the shared list design).
 */
class ItemCategoriesTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
    ];

    protected function sortable(): array
    {
        return ['name', 'items_count'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function baseQuery(): Builder
    {
        $query = ItemCategory::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
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

    /** Make a category active or inactive from its row menu. */
    public function toggleActive(int $id): void
    {
        $this->requirePermission('edit items');
        $category = ItemCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
        $this->successMessage = $category->is_active ? "{$category->name} is active again." : "{$category->name} made inactive.";
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit items',
            'deactivate' => 'edit items',
            'delete' => 'delete items',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one category first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                ItemCategory::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} categor".($count === 1 ? 'y' : 'ies').' made active.';
                break;

            case 'deactivate':
                ItemCategory::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} categor".($count === 1 ? 'y' : 'ies').' made inactive.';
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (ItemCategory::whereIn('id', $this->selectedItems)->get() as $category) {
                    if ($this->inUse($category)) {
                        $skipped++;

                        continue;
                    }
                    $category->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} categor".($deleted === 1 ? 'y' : 'ies').'.'.($skipped ? " Skipped {$skipped} with items or sub-categories." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked category has items or sub-categories.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one empty category from its row menu. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete items');
        $category = ItemCategory::findOrFail($id);
        if ($this->inUse($category)) {
            $this->errorMessage = "{$category->name} can't be deleted: it has items or sub-categories.";

            return;
        }
        $category->delete();
        $this->successMessage = "Deleted {$category->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function inUse(ItemCategory $category): bool
    {
        return $category->items()->exists() || $category->children()->exists();
    }

    public function render()
    {
        // Counts in the same query instead of loading every item (no query per row).
        $categories = $this->filteredQuery()
            ->with('parent:id,name')->withCount(['items', 'children'])
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('item_categories.id')
            ->paginate($this->pageSize());

        return view('livewire.items.item-categories-table', [
            'categories' => $categories,
            'tabs' => $this->tabs(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
