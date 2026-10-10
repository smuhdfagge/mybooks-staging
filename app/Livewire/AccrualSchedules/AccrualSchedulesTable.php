<?php

namespace App\Livewire\AccrualSchedules;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\AccrualSchedule;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Prepaid and deferred schedules (tables plan T4: the shared list design). */
class AccrualSchedulesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $type = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'type' => ['except' => ''],
    ];

    public const LABELS = ['active' => 'Running', 'completed' => 'Finished', 'cancelled' => 'Cancelled'];

    protected function sortable(): array
    {
        return ['start_date', 'schedule_number', 'total_amount', 'released_amount'];
    }

    protected function rowRelations(): array
    {
        return ['plAccount:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['type'];
    }

    protected function baseQuery(): Builder
    {
        $query = AccrualSchedule::query();
        if (($term = trim($this->search)) !== '') {
            $query->where(fn ($q) => $q->where('schedule_number', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%"));
        }
        if (array_key_exists($this->type, AccrualSchedule::TYPES)) {
            $query->where('type', $this->type);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.accrual-schedules.accrual-schedules-table', [
            'schedules' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, [], 'status', hideEmpty: true),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(released_amount), 0) as released')->first(),
            'types' => AccrualSchedule::TYPES,
            'filtered' => $this->isFiltered(),
        ]);
    }
}
