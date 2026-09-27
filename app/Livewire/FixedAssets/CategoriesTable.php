<?php

namespace App\Livewire\FixedAssets;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\FixedAssetCategory;

class CategoriesTable extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
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
        $tenantId = auth()->user()->tenant_id;

        $query = FixedAssetCategory::where('tenant_id', $tenantId);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        return $query->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
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

        switch ($this->bulkAction) {
            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;
                
                foreach ($this->selectedItems as $categoryId) {
                    $category = FixedAssetCategory::find($categoryId);
                    if (!$category) continue;
                    
                    // Check if category has assets
                    if ($category->assets()->exists()) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $category->delete();
                    $deletedCount++;
                }
                
                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} category(ies). Skipped {$skippedCount} category(ies) with existing assets.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} category(ies).";
                } else {
                    $this->errorMessage = "Could not delete any categories. All selected categories have existing assets.";
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

    public function render()
    {
        $tenantId = auth()->user()->tenant_id;

        $query = FixedAssetCategory::where('tenant_id', $tenantId)
            ->withCount('assets');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        $categories = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $depreciationMethods = FixedAssetCategory::getDepreciationMethods();

        return view('livewire.fixed-assets.categories-table', [
            'categories' => $categories,
            'depreciationMethods' => $depreciationMethods,
        ]);
    }
}
