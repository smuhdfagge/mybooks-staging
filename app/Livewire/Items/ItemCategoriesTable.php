<?php

namespace App\Livewire\Items;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\ItemCategory;
use Livewire\Component;
use Livewire\WithPagination;

class ItemCategoriesTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $showInactive = false;

    public $perPage = 15;

    public $successMessage = '';

    public $errorMessage = '';

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingShowInactive()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'showInactive']);
        $this->resetPage();
    }

    public function toggleActive(ItemCategory $category)
    {
        $this->requirePermission('edit items');

        $category->update(['is_active' => ! $category->is_active]);
        $this->successMessage = 'Category status updated.';
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredCategoryIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredCategoryIds());
    }

    private function getFilteredCategoryIds()
    {
        $query = ItemCategory::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        return $query->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
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
            $this->errorMessage = 'Please select at least one category.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                ItemCategory::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} category(ies).";
                break;

            case 'deactivate':
                ItemCategory::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} category(ies).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $categoryId) {
                    $category = ItemCategory::find($categoryId);
                    if (! $category) {
                        continue;
                    }

                    // Check if category has related records
                    if ($category->items()->exists() || $category->children()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $category->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} category(ies). Skipped {$skippedCount} category(ies) with existing items or subcategories.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} category(ies).";
                } else {
                    $this->errorMessage = 'Could not delete any categories. All selected categories have existing items or subcategories.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function deleteCategory($categoryId)
    {
        $this->requirePermission('delete items');

        $this->successMessage = '';
        $this->errorMessage = '';

        $category = ItemCategory::findOrFail($categoryId);

        if ($category->items()->exists()) {
            $this->errorMessage = 'Cannot delete category with existing items.';

            return;
        }

        if ($category->children()->exists()) {
            $this->errorMessage = 'Cannot delete category with subcategories.';

            return;
        }

        $category->delete();
        $this->successMessage = 'Category deleted successfully.';
    }

    public function render()
    {
        $query = ItemCategory::query()
            ->with(['parent', 'children', 'items'])
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        $categories = $query->paginate($this->pageSize());

        return view('livewire.items.item-categories-table', [
            'categories' => $categories,
        ]);
    }
}
