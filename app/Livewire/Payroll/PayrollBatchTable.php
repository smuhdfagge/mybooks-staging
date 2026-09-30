<?php

namespace App\Livewire\Payroll;

use App\Livewire\Concerns\LimitsPageSize;
use App\Models\PayrollBatch;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollBatchTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
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

    public function render()
    {
        $batches = PayrollBatch::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('batch_number', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        return view('livewire.payroll.payroll-batch-table', [
            'batches' => $batches,
        ]);
    }
}
