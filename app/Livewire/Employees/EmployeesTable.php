<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\ChecksPermissions;
use App\Models\Department;
use App\Models\Employee;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeesTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';

    public $status = '';

    public $department = '';

    public $sortField = 'first_name';

    public $sortDirection = 'asc';

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
        'department' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingDepartment()
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
            $this->selectedItems = $this->getFilteredEmployeeIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredEmployeeIds());
    }

    private function getFilteredEmployeeIds()
    {
        return Employee::query()
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('employee_id', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
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
            'activate' => 'edit employees',
            'deactivate' => 'edit employees',
            'delete' => 'delete employees',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one employee.';

            return;
        }

        if (empty($this->bulkAction)) {
            $this->errorMessage = 'Please select an action.';

            return;
        }

        $count = count($this->selectedItems);

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                Employee::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['status' => 'active']);
                $this->successMessage = "Successfully activated {$count} employee(s).";
                break;

            case 'deactivate':
                Employee::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->update(['status' => 'inactive']);
                $this->successMessage = "Successfully deactivated {$count} employee(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($this->selectedItems as $employeeId) {
                    $employee = Employee::find($employeeId);
                    if (! $employee) {
                        continue;
                    }

                    // Check if employee has related records
                    if ($employee->payrolls()->exists()) {
                        $skippedCount++;

                        continue;
                    }

                    $employee->delete();
                    $deletedCount++;
                }

                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} employee(s). Skipped {$skippedCount} employee(s) with payroll records.";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} employee(s).";
                } else {
                    $this->errorMessage = 'Could not delete any employees. All selected employees have payroll records.';
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
        $employees = Employee::query()
            ->with(['department', 'designation'])
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('employee_id', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $departments = Department::where('is_active', true)->get();

        return view('livewire.employees.employees-table', [
            'employees' => $employees,
            'departments' => $departments,
        ]);
    }
}
