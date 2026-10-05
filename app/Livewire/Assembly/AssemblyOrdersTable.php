<?php

namespace App\Livewire\Assembly;

use App\Enums\AssemblyOrderStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\AssemblyOrder;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

/** Assembly orders list (session 14), filtered by status, kind and warehouse. */
class AssemblyOrdersTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $kind = '';

    public $warehouse = '';

    public $perPage = 15;

    public $sortField = 'assembly_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'kind' => ['except' => ''],
        'warehouse' => ['except' => ''],
    ];

    private const SORTABLE = ['order_number', 'assembly_date', 'status', 'total_cost'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingKind()
    {
        $this->resetPage();
    }

    public function updatingWarehouse()
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }
        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
    }

    public function render()
    {
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'assembly_date';
        $warehouseId = (int) $this->warehouse;

        $orders = AssemblyOrder::with(['billOfMaterial.item', 'warehouse', 'toWarehouse'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('billOfMaterial', fn ($b) => $b->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('billOfMaterial.item', fn ($i) => $i->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(in_array($this->status, AssemblyOrderStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->when(in_array($this->kind, [AssemblyOrder::KIND_BUILD, AssemblyOrder::KIND_BREAKDOWN], true), fn ($q) => $q->where('kind', $this->kind))
            ->when($warehouseId, fn ($q) => $q->where(fn ($w) => $w->where('warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        $warehouses = Warehouse::moduleOn() ? Warehouse::orderByDesc('is_default')->orderBy('name')->get(['id', 'name']) : collect();

        return view('livewire.assembly.assembly-orders-table', [
            'orders' => $orders,
            'statuses' => AssemblyOrderStatus::cases(),
            'warehouses' => $warehouses->count() > 1 ? $warehouses : collect(),
        ]);
    }
}
