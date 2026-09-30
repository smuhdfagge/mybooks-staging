<?php

namespace App\Livewire\Leaves;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\LimitsPageSize;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use Livewire\Component;
use Livewire\WithPagination;

class LeavesTable extends Component
{
    use ChecksPermissions, LimitsPageSize, WithPagination;

    public $search = '';

    public $status = '';

    public $leaveType = '';

    public $employee = '';

    public $sortField = 'start_date';

    public $sortDirection = 'desc';

    public $perPage = 10;

    // Bulk operation properties
    public $selectedItems = [];

    public $selectAll = false;

    public $bulkAction = '';

    public $successMessage = '';

    public $errorMessage = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'leaveType' => ['except' => ''],
        'employee' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingLeaveType()
    {
        $this->resetPage();
    }

    public function updatingEmployee()
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

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedItems = $this->getFilteredLeaveIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredLeaveIds());
    }

    private function getFilteredLeaveIds()
    {
        return Leave::query()
            ->with(['employee'])
            ->when($this->search, fn ($q) => $q->whereHas('employee', function ($query) {
                $query->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->leaveType, fn ($q) => $q->where('leave_type_id', $this->leaveType))
            ->when($this->employee, fn ($q) => $q->where('employee_id', $this->employee))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
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
            $this->errorMessage = 'Please select at least one leave request.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'approve':
                $approvedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $leaveId) {
                    $leave = Leave::find($leaveId);
                    if (! $leave || $leave->status !== 'pending') {
                        $skippedCount++;

                        continue;
                    }

                    $leave->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    $approvedCount++;
                }

                if ($approvedCount > 0) {
                    $this->successMessage = "Approved {$approvedCount} leave request(s).";
                    if ($skippedCount > 0) {
                        $this->successMessage .= " Skipped {$skippedCount} non-pending request(s).";
                    }
                } else {
                    $this->errorMessage = 'No pending leave requests to approve.';
                }
                break;

            case 'reject':
                $rejectedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $leaveId) {
                    $leave = Leave::find($leaveId);
                    if (! $leave || $leave->status !== 'pending') {
                        $skippedCount++;

                        continue;
                    }

                    $leave->update([
                        'status' => 'rejected',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    $rejectedCount++;
                }

                if ($rejectedCount > 0) {
                    $this->successMessage = "Rejected {$rejectedCount} leave request(s).";
                    if ($skippedCount > 0) {
                        $this->successMessage .= " Skipped {$skippedCount} non-pending request(s).";
                    }
                } else {
                    $this->errorMessage = 'No pending leave requests to reject.';
                }
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $leaveId) {
                    $leave = Leave::find($leaveId);
                    if (! $leave) {
                        continue;
                    }

                    if ($leave->status !== 'pending') {
                        $skippedCount++;

                        continue;
                    }

                    $leave->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} leave request(s). Skipped {$skippedCount} non-pending request(s).";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} leave request(s).";
                } else {
                    $this->errorMessage = 'Could not delete any leave requests. Only pending leaves can be deleted.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function render()
    {
        $leaves = Leave::query()
            ->with(['employee', 'leaveType', 'approvedBy'])
            ->when($this->search, fn ($q) => $q->whereHas('employee', function ($query) {
                $query->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->leaveType, fn ($q) => $q->where('leave_type_id', $this->leaveType))
            ->when($this->employee, fn ($q) => $q->where('employee_id', $this->employee))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->pageSize());

        $leaveTypes = LeaveType::where('is_active', true)->get();
        $employees = Employee::where('status', 'active')->get();

        return view('livewire.leaves.leaves-table', [
            'leaves' => $leaves,
            'leaveTypes' => $leaveTypes,
            'employees' => $employees,
        ]);
    }
}
