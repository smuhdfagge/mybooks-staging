<?php

namespace App\Livewire\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

/** Stock transfers list (session 13), filtered by status and warehouse. */
class StockTransfersTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $warehouse = '';

    public $perPage = 15;

    public $sortField = 'transfer_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'warehouse' => ['except' => ''],
    ];

    private const SORTABLE = ['transfer_number', 'transfer_date', 'status'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
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
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'transfer_date';
        $warehouseId = (int) $this->warehouse;

        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse'])
            ->withCount('items')
            ->withSum('items', 'shipped_cost')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('transfer_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('items.item', fn ($i) => $i->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(in_array($this->status, StockTransferStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->when($warehouseId, fn ($q) => $q->where(fn ($w) => $w->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        return view('livewire.stock-transfers.stock-transfers-table', [
            'transfers' => $transfers,
            'statuses' => StockTransferStatus::cases(),
            'warehouses' => Warehouse::orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
