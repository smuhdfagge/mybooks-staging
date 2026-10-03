<?php

namespace App\Livewire\AccrualSchedules;

use App\Enums\AccrualScheduleStatus;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\AccrualSchedule;
use Livewire\Component;
use Livewire\WithPagination;

/** List of prepaid expense and deferred revenue schedules (S9). */
class AccrualSchedulesTable extends Component
{
    use LimitsPageSize, WithPagination;

    public $search = '';

    public $type = '';

    public $status = '';

    public $perPage = 15;

    public $sortField = 'start_date';

    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    private const SORTABLE = ['schedule_number', 'description', 'start_date', 'total_amount', 'released_amount', 'status'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingType()
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
        $sort = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'start_date';

        $schedules = AccrualSchedule::with(['plAccount', 'balanceAccount'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('schedule_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%');
                });
            })
            ->when(array_key_exists($this->type, AccrualSchedule::TYPES), fn ($q) => $q->where('type', $this->type))
            ->when(in_array($this->status, AccrualScheduleStatus::values(), true), fn ($q) => $q->where('status', $this->status))
            ->orderBy($sort, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->pageSize());

        return view('livewire.accrual-schedules.accrual-schedules-table', [
            'schedules' => $schedules,
            'statuses' => AccrualScheduleStatus::cases(),
        ]);
    }
}
