<?php

namespace App\Livewire\Inventory;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Inventory;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Concerns\ChecksPermissions;

class InventoryTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $stockFilter = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 15;

    // Bulk operation properties
    public $selectedItems = [];
    public $selectAll = false;
    public $bulkAction = '';
    public $successMessage = '';
    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'stockFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingStockFilter()
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
            $this->selectedItems = $this->getFilteredItemIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredItemIds());
    }

    private function getFilteredItemIds()
    {
        $query = Item::query()->where('track_inventory', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        if ($this->stockFilter) {
            switch ($this->stockFilter) {
                case 'in_stock':
                    $query->whereHas('inventory', fn($q) => $q->where('quantity', '>', 0));
                    break;
                case 'low_stock':
                    $query->where('reorder_level', '>', 0)
                          ->whereHas('inventory', fn($q) => $q->whereColumn('quantity', '<=', 'items.reorder_level'));
                    break;
                case 'out_of_stock':
                    $query->where(function($q) {
                        $q->whereHas('inventory', fn($iq) => $iq->where('quantity', '<=', 0))
                          ->orWhereDoesntHave('inventory');
                    });
                    break;
            }
        }

        return $query->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'reset_quantity' => 'adjust inventory',
            'disable_tracking' => 'adjust inventory',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one item.';
            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';
            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'reset_quantity':
                foreach ($this->selectedItems as $itemId) {
                    $inventory = Inventory::where('item_id', $itemId)->first();
                    if ($inventory) {
                        $inventory->update(['quantity' => 0]);
                    }
                }
                $this->successMessage = "Successfully reset quantity for {$count} item(s).";
                break;

            case 'disable_tracking':
                Item::whereIn('id', $this->selectedItems)->update(['track_inventory' => false]);
                $this->successMessage = "Successfully disabled inventory tracking for {$count} item(s).";
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
        $query = Item::query()
            ->where('track_inventory', true)
            ->with(['category', 'inventory']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        if ($this->stockFilter) {
            switch ($this->stockFilter) {
                case 'in_stock':
                    $query->whereHas('inventory', fn($q) => $q->where('quantity', '>', 0));
                    break;
                case 'low_stock':
                    $query->where('reorder_level', '>', 0)
                          ->whereHas('inventory', fn($q) => $q->whereColumn('quantity', '<=', 'items.reorder_level'));
                    break;
                case 'out_of_stock':
                    $query->where(function($q) {
                        $q->whereHas('inventory', fn($iq) => $iq->where('quantity', '<=', 0))
                          ->orWhereDoesntHave('inventory');
                    });
                    break;
            }
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.inventory.inventory-table', [
            'items' => $query->paginate($this->perPage),
            'categories' => ItemCategory::where('is_active', true)->get(),
        ]);
    }
}
