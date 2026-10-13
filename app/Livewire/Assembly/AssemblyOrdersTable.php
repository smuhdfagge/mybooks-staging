<?php

namespace App\Livewire\Assembly;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\AssemblyOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Assembly orders list (tables plan T4: the shared list design). */
class AssemblyOrdersTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $kind = '';

    public string $warehouse = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'kind' => ['except' => ''],
        'warehouse' => ['except' => ''],
    ];

    public const LABELS = ['draft' => 'Draft', 'completed' => 'Done', 'cancelled' => 'Cancelled'];

    protected function sortable(): array
    {
        return ['assembly_date', 'order_number', 'total_cost'];
    }

    protected function rowRelations(): array
    {
        return ['billOfMaterial.item:id,name,unit', 'warehouse:id,name', 'toWarehouse:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'kind', 'warehouse'];
    }

    protected function baseQuery(): Builder
    {
        $query = AssemblyOrder::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")
                ->orWhereHas('billOfMaterial', fn ($b) => $b->where('name', 'like', "%{$term}%"))
                ->orWhereHas('billOfMaterial.item', fn ($i) => $i->where('name', 'like', "%{$term}%")));
        }
        if (in_array($this->kind, [AssemblyOrder::KIND_BUILD, AssemblyOrder::KIND_BREAKDOWN], true)) {
            $query->where('kind', $this->kind);
        }
        if ($this->warehouse !== '' && ctype_digit($this->warehouse)) {
            $w = (int) $this->warehouse;
            $query->where(fn ($q) => $q->where('warehouse_id', $w)->orWhere('to_warehouse_id', $w));
        }

        return $this->applyPeriod($query, 'assembly_date', $this->period);
    }

    public function render()
    {
        $warehouses = Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->pluck('name', 'id') : collect();

        return view('livewire.assembly.assembly-orders-table', [
            'orders' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw("COUNT(*) as n, COALESCE(SUM(CASE WHEN status = 'completed' THEN total_cost ELSE 0 END), 0) as cost")->first(),
            'warehouses' => $warehouses->count() > 1 ? $warehouses : collect(),
            'kinds' => [AssemblyOrder::KIND_BUILD => 'Make', AssemblyOrder::KIND_BREAKDOWN => 'Break down'],
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
