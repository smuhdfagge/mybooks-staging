<?php

namespace App\Livewire\FixedAssets;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Fixed asset categories (tables plan T4: the shared list design). */
class CategoriesTable extends Component
{
    use ChecksPermissions, ListTable;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['name', 'default_useful_life', 'assets_count'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function decorateRows(Builder $query): Builder
    {
        return $query->withCount('assets');
    }

    protected function baseQuery(): Builder
    {
        $query = FixedAssetCategory::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        return $query;
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'delete' => 'delete fixed-assets',
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

        $this->authorizeBulkAction();

        if ($this->bulkAction !== 'delete') {
            $this->errorMessage = 'Invalid action selected.';

            return;
        }

        $deleted = 0;
        $skipped = 0;
        foreach (FixedAssetCategory::whereIn('id', $this->selectedItems)->get() as $category) {
            if ($category->assets()->exists()) {
                $skipped++;

                continue;
            }
            $category->delete();
            $deleted++;
        }
        if ($deleted > 0) {
            $this->successMessage = "Deleted {$deleted} categor".($deleted === 1 ? 'y' : 'ies').'.'.($skipped ? " Skipped {$skipped} with assets." : '');
        } else {
            $this->errorMessage = 'None deleted: every ticked category has assets.';
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete fixed-assets');
        $category = FixedAssetCategory::findOrFail($id);
        if ($category->assets()->exists()) {
            $this->errorMessage = "{$category->name} has assets, so it can't be deleted.";

            return;
        }
        $category->delete();
        $this->successMessage = "Deleted {$category->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.fixed-assets.categories-table', [
            'categories' => $this->rows(),
            'methods' => FixedAssetCategory::getDepreciationMethods(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
