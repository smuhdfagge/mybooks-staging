<?php

namespace App\Livewire\StockTransfers;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Stock transfers list (tables plan T4: the shared list design). */
class StockTransfersTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $warehouse = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'warehouse' => ['except' => ''],
    ];

    public const LABELS = ['draft' => 'Draft', 'in_transit' => 'In transit', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected function sortable(): array
    {
        return ['transfer_date', 'transfer_number'];
    }

    protected function rowRelations(): array
    {
        return ['fromWarehouse:id,name', 'toWarehouse:id,name'];
    }

    protected function decorateRows(Builder $query): Builder
    {
        return $query->withCount('items')->withSum('items', 'shipped_cost');
    }

    protected function filterProperties(): array
    {
        return ['period', 'warehouse'];
    }

    protected function baseQuery(): Builder
    {
        $query = StockTransfer::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('transfer_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('items.item', fn ($i) => $i->where('name', 'like', "%{$term}%")));
        }
        if ($this->warehouse !== '' && ctype_digit($this->warehouse)) {
            $w = (int) $this->warehouse;
            $query->where(fn ($q) => $q->where('from_warehouse_id', $w)->orWhere('to_warehouse_id', $w));
        }

        return $this->applyPeriod($query, 'transfer_date', $this->period);
    }

    public function render()
    {
        return view('livewire.stock-transfers.stock-transfers-table', [
            'transfers' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'warehouses' => Warehouse::orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
