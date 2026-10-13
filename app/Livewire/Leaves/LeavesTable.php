<?php

namespace App\Livewire\Leaves;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\ActivityLog;
use App\Models\Leave;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Leave requests (tables plan T5: the shared list design). */
class LeavesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $period = '';

    public string $leaveType = '';

    public string $employee = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'period' => ['except' => ''],
        'leaveType' => ['except' => '', 'as' => 'type'],
        'employee' => ['except' => ''],
    ];

    public const LABELS = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    protected function sortable(): array
    {
        return ['start_date', 'days'];
    }

    protected function rowRelations(): array
    {
        return ['employee:id,first_name,last_name,employee_id', 'leaveType:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['period', 'leaveType', 'employee'];
    }

    protected function baseQuery(): Builder
    {
        $query = Leave::query();
        if (($term = trim($this->search)) !== '') {
            $query->whereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('employee_id', 'like', "%{$term}%"));
        }
        if ($this->leaveType !== '' && ctype_digit($this->leaveType)) {
            $query->where('leave_type_id', (int) $this->leaveType);
        }
        if ($this->employee !== '' && ctype_digit($this->employee)) {
            $query->where('employee_id', (int) $this->employee);
        }

        return $this->applyPeriod($query, 'start_date', $this->period);
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'approve' => 'approve leaves',
            'reject' => 'approve leaves',
            'delete' => 'delete leaves',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one request first.';

            return;
        }

        $this->authorizeBulkAction();
        $pending = fn () => Leave::with('employee')->whereIn('id', $this->selectedItems)->where('status', 'pending')->get();

        switch ($this->bulkAction) {
            case 'approve':
            case 'reject':
                $status = $this->bulkAction === 'approve' ? 'approved' : 'rejected';
                $n = 0;
                foreach ($pending() as $leave) {
                    $this->answer($leave, $status);
                    $n++;
                }
                $this->successMessage = ucfirst($status)." {$n} request(s). Only requests still waiting change.";
                break;

            case 'delete':
                $n = 0;
                foreach ($pending() as $leave) {
                    $leave->delete();
                    $n++;
                }
                $this->successMessage = "Deleted {$n} request(s). Only requests still waiting can be deleted.";
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Approve one waiting request from its row menu, staying on the list. */
    public function approveOne(int $id): void
    {
        $this->requirePermission('approve leaves');
        $this->reset(['successMessage', 'errorMessage']);
        $leave = Leave::with('employee')->findOrFail($id);
        if ($leave->status !== 'pending') {
            $this->errorMessage = 'This request has already been answered.';

            return;
        }
        $this->answer($leave, 'approved');
        $this->successMessage = "Approved leave for {$leave->employee->full_name}.";
    }

    /** Same record and log entry as approving or rejecting from the leave's own page. */
    private function answer(Leave $leave, string $status): void
    {
        $leave->update(['status' => $status, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $leave->logCustomActivity($status === 'approved' ? ActivityLog::ACTION_APPROVED : ActivityLog::ACTION_REJECTED, "Leave request for {$leave->employee->first_name} {$leave->employee->last_name} was {$status}");
    }

    /** Delete one request still waiting for an answer. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete leaves');
        $leave = Leave::findOrFail($id);
        if ($leave->status !== 'pending') {
            $this->errorMessage = 'Only requests still waiting can be deleted.';

            return;
        }
        $leave->delete();
        $this->successMessage = 'Request deleted.';
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.leaves.leaves-table', [
            'leaves' => $this->rows(),
            'tabs' => $this->statusTabs(self::LABELS, ['pending'], 'status', hideEmpty: false),
            'totals' => $this->filteredQuery()->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(days), 0) as days')->first(),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'periods' => self::periodOptions(),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
