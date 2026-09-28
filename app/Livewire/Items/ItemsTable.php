<?php

namespace App\Livewire\Items;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Item;
use App\Models\ItemCategory;
use Livewire\Component;
use Livewire\WithPagination;

class ItemsTable extends Component
{
    use ChecksPermissions, WithPagination;

    public string $search = '';

    public string $type = '';

    public string $category = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'category' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingType()
    {
        $this->resetPage();
    }

    public function updatingCategory()
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
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
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
        return Item::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->pluck('id')
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
            case 'activate':
                Item::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "Successfully activated {$count} item(s).";
                break;

            case 'deactivate':
                Item::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "Successfully deactivated {$count} item(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $itemId) {
                    $item = Item::find($itemId);
                    if (! $item) {
                        continue;
                    }

                    // Check if item has related records
                    if ($item->invoiceItems()->exists() || $item->billItems()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $item->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} item(s). Skipped {$skippedCount} item(s) with existing records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} item(s).";
                } else {
                    $this->errorMessage = 'Could not delete any items. All selected items have existing records.';
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

    public function deleteItem($itemId)
    {
        $this->requirePermission('delete items');

        $item = Item::findOrFail($itemId);

        // Check if item has related records that prevent deletion
        if ($item->invoiceItems()->exists()) {
            session()->flash('error', 'Cannot delete item with existing invoice items.');

            return;
        }

        if ($item->billItems()->exists()) {
            session()->flash('error', 'Cannot delete item with existing bill items.');

            return;
        }

        $item->delete();

        session()->flash('success', 'Item deleted successfully.');
    }

    public function render()
    {
        $items = Item::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $categories = ItemCategory::where('is_active', true)->get();

        return view('livewire.items.items-table', [
            'items' => $items,
            'categories' => $categories,
        ]);
    }
}
