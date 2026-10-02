<?php

namespace App\Livewire\Quotations;

use App\Enums\QuotationStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\Quotation;
use Livewire\Component;
use Livewire\WithPagination;

class QuotationsTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $perPage = 15;

    public $sortField = 'quotation_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    private const SORTABLE = ['quotation_number', 'quotation_date', 'expiry_date', 'total', 'status'];

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
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'quotation_date';

        $quotations = Quotation::with('customer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('quotation_number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('company_name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(in_array($this->status, QuotationStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        return view('livewire.quotations.quotations-table', [
            'quotations' => $quotations,
            'statuses' => QuotationStatus::cases(),
        ]);
    }
}
