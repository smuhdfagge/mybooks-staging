<?php

namespace App\Livewire\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Stock list (tables plan T4): what is on hand, item by item, for all
 * warehouses or one, with what it is worth.
 */
class InventoryTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $category = '';

    public string $warehouse = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'stock'],
        'category' => ['except' => ''],
        'warehouse' => ['except' => ''],
    ];

    protected function sortable(): array
    {
        return ['name', 'on_hand', 'stock_value', 'reorder_level'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'name';
            $this->sortDirection = 'asc';
        }
    }

    protected function filterProperties(): array
    {
        return ['category', 'warehouse'];
    }

    private function warehouseId(): ?int
    {
        if ($this->warehouse === '' || ! ctype_digit($this->warehouse)) {
            return null;
        }

        return Warehouse::whereKey((int) $this->warehouse)->exists() ? (int) $this->warehouse : null;
    }

    private function onHand(): string
    {
        return Item::onHandSql($this->warehouseId());
    }

    /** Stock value: quantity at each warehouse's average cost. */
    private function valueSql(): string
    {
        $where = ($w = $this->warehouseId()) ? ' AND inventories.warehouse_id = '.$w : '';

        return "(SELECT COALESCE(SUM(inventories.quantity * inventories.unit_cost), 0) FROM inventories WHERE inventories.item_id = items.id{$where})";
    }

    protected function baseQuery(): Builder
    {
        $query = Item::query()->where('track_inventory', true);
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($this->category !== '' && ctype_digit($this->category)) {
            $query->where('category_id', (int) $this->category);
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        $onHand = $this->onHand();

        return match ($tab) {
            'in' => $query->whereRaw("{$onHand} > 0"),
            // Same rule as the dashboard: at or below the reorder level (out of stock included).
            'low' => $query->where('reorder_level', '>', 0)->whereRaw("{$onHand} <= items.reorder_level"),
            'out' => $query->whereRaw("{$onHand} <= 0"),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one query */
    private function tabs(): array
    {
        $onHand = $this->onHand();
        $row = $this->baseQuery()->toBase()->selectRaw(
            "COUNT(*) as all_rows,
             SUM(CASE WHEN {$onHand} > 0 THEN 1 ELSE 0 END) as in_stock,
             SUM(CASE WHEN reorder_level > 0 AND {$onHand} <= reorder_level THEN 1 ELSE 0 END) as low,
             SUM(CASE WHEN {$onHand} <= 0 THEN 1 ELSE 0 END) as out_of_stock"
        )->first();

        return [
            '' => ['label' => 'All', 'count' => (int) $row->all_rows, 'alert' => false],
            'in' => ['label' => 'In stock', 'count' => (int) $row->in_stock, 'alert' => false],
            'low' => ['label' => 'Running low', 'count' => (int) $row->low, 'alert' => true],
            'out' => ['label' => 'Out of stock', 'count' => (int) $row->out_of_stock, 'alert' => true],
        ];
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
            $this->errorMessage = 'Tick at least one item first.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'reset_quantity':
                // Through AdjustStock, so the Inventory account moves with the stock.
                $reset = 0;
                $problems = [];
                $records = Inventory::with('item')->whereIn('item_id', $this->selectedItems)
                    ->when($this->warehouseId(), fn ($q, $w) => $q->where('warehouse_id', $w))
                    ->where('quantity', '!=', 0)->get();
                foreach ($records as $record) {
                    try {
                        app(AdjustStock::class)->handle($record->item, AdjustStock::SET, 0, warehouseId: (int) $record->warehouse_id,
                            reason: 'Reset to zero from the stock list', label: 'Stock reset');
                        $reset++;
                    } catch (ValidationException $e) {
                        $problems[] = $record->item->name.': '.collect($e->errors())->flatten()->first();
                    }
                }
                $this->successMessage = "Set {$reset} stock record(s) to zero.";
                if ($problems) {
                    $this->errorMessage = count($problems).' could not be set to zero: '.implode(' ', $problems);
                }
                break;

            case 'disable_tracking':
                Item::whereIn('id', $this->selectedItems)->update(['track_inventory' => false]);
                $this->successMessage = "Stopped counting stock for {$count} item(s).";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    public function render()
    {
        $reserved = Item::onHandSql($this->warehouseId(), 'reserved_quantity');
        $items = $this->filteredQuery()
            ->select('items.*')
            ->selectRaw($this->onHand().' as on_hand')
            ->selectRaw($reserved.' as reserved')
            ->selectRaw($this->valueSql().' as stock_value')
            ->with('category:id,name')
            ->orderBy($this->sortColumn(), $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderBy('items.id')
            ->paginate($this->pageSize());

        $warehouses = Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->pluck('name', 'id') : collect();

        return view('livewire.inventory.inventory-table', [
            'items' => $items,
            'tabs' => $this->tabs(),
            'totals' => (object) [
                'n' => $items->total(),
                'value' => (float) $this->filteredQuery()->toBase()->selectRaw('COALESCE(SUM('.$this->valueSql().'), 0) as v')->value('v'),
            ],
            'categories' => ItemCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses' => $warehouses->count() > 1 ? $warehouses : collect(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
