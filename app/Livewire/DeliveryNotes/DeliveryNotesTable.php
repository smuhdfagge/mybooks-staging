<?php

namespace App\Livewire\DeliveryNotes;

use App\Enums\DeliveryNoteStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\DeliveryNote;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveryNotesTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $perPage = 15;

    public $sortField = 'delivery_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    private const SORTABLE = ['delivery_number', 'delivery_date', 'status'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
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
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'delivery_date';

        $notes = DeliveryNote::with(['customer', 'salesOrder'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('delivery_number', 'like', '%'.$this->search.'%')
                        ->orWhere('tracking_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('salesOrder', fn ($o) => $o->where('order_number', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(in_array($this->status, DeliveryNoteStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        return view('livewire.delivery-notes.delivery-notes-table', [
            'notes' => $notes,
            'statuses' => array_filter(DeliveryNoteStatus::cases(), fn ($s) => $s !== DeliveryNoteStatus::InTransit),
        ]);
    }
}
