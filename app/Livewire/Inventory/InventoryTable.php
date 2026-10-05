<?php

namespace App\Livewire\Inventory;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $categoryFilter = '';

    public $stockFilter = '';

    /** Show one warehouse's stock (session 12); empty = all warehouses. */
    public $warehouseFilter = '';

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
        'warehouseFilter' => ['except' => ''],
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

    public function updatingWarehouseFilter()
    {
        $this->resetPage();
    }

    /** The chosen warehouse, if it is one of this business's. */
    private function warehouseId(): ?int
    {
        if ($this->warehouseFilter === '' || $this->warehouseFilter === null) {
            return null;
        }

        return Warehouse::whereKey((int) $this->warehouseFilter)->exists() ? (int) $this->warehouseFilter : null;
    }

    /** In stock / low / out, on the item's total or one warehouse's stock. */
    private function applyStockFilter($query): void
    {
        $onHand = Item::onHandSql($this->warehouseId());

        switch ($this->stockFilter) {
            case 'in_stock':
                $query->whereRaw("{$onHand} > 0");
                break;
            case 'low_stock':
                $query->where('reorder_level', '>', 0)->whereRaw("{$onHand} <= items.reorder_level");
                break;
            case 'out_of_stock':
                $query->whereRaw("{$onHand} <= 0");
                break;
        }
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
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $this->applyStockFilter($query);

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
                    // Every warehouse's record, or only the one shown (session 12).
                    Inventory::where('item_id', $itemId)
                        ->when($this->warehouseId(), fn ($q, $w) => $q->where('warehouse_id', $w))
                        ->update(['quantity' => 0]);
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
            ->with(['category', 'inventory' => fn ($q) => $q->when($this->warehouseId(), fn ($w, $id) => $w->where('inventories.warehouse_id', $id))]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $this->applyStockFilter($query);

        $query->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.inventory.inventory-table', [
            'items' => $query->paginate($this->pageSize()),
            'categories' => ItemCategory::where('is_active', true)->get(),
            'warehouses' => Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->get() : collect(),
        ]);
    }
}
