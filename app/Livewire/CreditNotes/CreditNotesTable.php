<?php

namespace App\Livewire\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\CreditNote;
use Livewire\Component;
use Livewire\WithPagination;

class CreditNotesTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $perPage = 15;

    public $sortField = 'credit_note_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    private const SORTABLE = ['credit_note_number', 'credit_note_date', 'status', 'total', 'balance'];

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
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'credit_note_date';

        $notes = CreditNote::with(['customer', 'invoice'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('credit_note_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(in_array($this->status, CreditNoteStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        return view('livewire.credit-notes.credit-notes-table', [
            'notes' => $notes,
            'statuses' => CreditNoteStatus::cases(),
        ]);
    }
}
