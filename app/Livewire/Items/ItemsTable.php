<?php

namespace App\Livewire\Items;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Items list (tables plan T4: the shared list design, see InvoicesTable).
 */
class ItemsTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $category = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'show'],
        'category' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['name', 'sku', 'selling_price', 'cost_price', 'on_hand'];
    }

    /** Items sort by name A–Z first. */
    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function filterProperties(): array
    {
        return ['category'];
    }

    protected function baseQuery(): Builder
    {
        $query = Item::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }
        if ($this->category !== '' && ctype_digit($this->category)) {
            $query->where('category_id', (int) $this->category);
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'products' => $query->where('type', 'product'),
            'services' => $query->where('type', 'service'),
            'low' => $query->where('track_inventory', true)->where('reorder_level', '>', 0)->whereRaw(Item::onHandSql().' <= items.reorder_level'),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one query */
    private function tabs(): array
    {
        $onHand = Item::onHandSql();
        $row = $this->baseQuery()->toBase()->selectRaw(
            "COUNT(*) as all_rows,
             SUM(CASE WHEN type = 'product' THEN 1 ELSE 0 END) as products,
             SUM(CASE WHEN type = 'service' THEN 1 ELSE 0 END) as services,
             SUM(CASE WHEN track_inventory = 1 AND reorder_level > 0 AND {$onHand} <= reorder_level THEN 1 ELSE 0 END) as low,
             SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive"
        )->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'products' => ['label' => 'Goods', 'count' => (int) $row->products, 'alert' => false],
            'services' => ['label' => 'Services', 'count' => (int) $row->services, 'alert' => false],
            'low' => ['label' => 'Running low', 'count' => (int) $row->low, 'alert' => true],
            'inactive' => ['label' => 'Inactive', 'count' => (int) $row->inactive, 'alert' => false],
        ];
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
            $this->errorMessage = 'Tick at least one item first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Item::whereIn('id', $this->selectedItems)->update(['is_active' => true]);
                $this->successMessage = "{$count} item(s) made active.";
                break;

            case 'deactivate':
                Item::whereIn('id', $this->selectedItems)->update(['is_active' => false]);
                $this->successMessage = "{$count} item(s) made inactive.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (Item::whereIn('id', $this->selectedItems)->get() as $item) {
                    if ($this->inUse($item)) {
                        $skipped++;

                        continue;
                    }
                    $item->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} item(s).".($skipped ? " Skipped {$skipped} used on invoices or bills." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked item is used on invoices or bills. Make them inactive instead.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one item from its row menu (items on invoices or bills are kept). */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete items');
        $item = Item::findOrFail($id);
        if ($this->inUse($item)) {
            $this->errorMessage = "{$item->name} can't be deleted: it is on invoices or bills. Make it inactive instead.";

            return;
        }
        $item->delete();
        $this->successMessage = "Deleted {$item->name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    private function inUse(Item $item): bool
    {
        return $item->invoiceItems()->exists() || $item->billItems()->exists();
    }

    public function render()
    {
        // Stock across all warehouses, in the same query (no query per row).
        $items = $this->filteredQuery()
            ->select('items.*')->selectRaw(Item::onHandSql().' as on_hand')
            ->with('category:id,name')
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('items.id')
            ->paginate($this->pageSize());

        return view('livewire.items.items-table', [
            'items' => $items,
            'tabs' => $this->tabs(),
            'categories' => ItemCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
