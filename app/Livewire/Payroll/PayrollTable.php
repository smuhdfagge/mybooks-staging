<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Livewire\Concerns\ChecksPermissions;

class PayrollTable extends Component
{
    use ChecksPermissions, WithPagination;

    public $search = '';
    public $status = '';
    public $employee = '';
    public $sortField = 'created_at';
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
            $this->selectedItems = $this->getFilteredPayrollIds();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems()
    {
        $this->selectAll = count($this->selectedItems) === count($this->getFilteredPayrollIds());
    }

    private function getFilteredPayrollIds()
    {
        return Payroll::query()
            ->when($this->search, fn($q) => $q->where(function($query) {
                $query->where('payroll_number', 'like', "%{$this->search}%")
                    ->orWhereHas('employee', function($q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                          ->orWhere('last_name', 'like', "%{$this->search}%");
                    });
            }))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->employee, fn($q) => $q->where('employee_id', $this->employee))
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'approve' => 'approve payroll',
            'mark_paid' => 'edit payroll',
            'cancel' => 'edit payroll',
            'delete' => 'delete payroll',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Please select at least one payroll.';
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
                Payroll::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'draft')
                    ->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                $this->successMessage = "Successfully approved selected payroll(s).";
                break;

            case 'mark_paid':
                $payrolls = Payroll::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'approved')
                    ->get();

                $paidCount = 0;
                $failedCount = 0;

                DB::beginTransaction();
                try {
                    foreach ($payrolls as $payroll) {
                        if ($payroll->markAsPaid()) {
                            $payroll->logCustomActivity(ActivityLog::ACTION_PAID, "Payroll '{$payroll->payroll_number}' was marked as paid");
                            $paidCount++;
                        } else {
                            $failedCount++;
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->errorMessage = "Failed to mark payroll as paid: " . $e->getMessage();
                    break;
                }

                if ($paidCount > 0 && $failedCount > 0) {
                    $this->successMessage = "Marked {$paidCount} payroll(s) as paid. {$failedCount} could not be processed.";
                } elseif ($paidCount > 0) {
                    $this->successMessage = "Successfully marked {$paidCount} payroll(s) as paid.";
                } else {
                    $this->errorMessage = "No payrolls could be marked as paid.";
                }
                break;

            case 'cancel':
                Payroll::whereIn('id', $this->selectedItems)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->whereIn('status', ['draft', 'approved'])
                    ->update(['status' => 'cancelled']);
                $this->successMessage = "Successfully cancelled selected payroll(s).";
                break;

            case 'delete':
                $deletedCount = 0;
                $skippedCount = 0;
                
                foreach ($this->selectedItems as $payrollId) {
                    $payroll = Payroll::find($payrollId);
                    if (!$payroll) continue;
                    
                    // Only allow deletion of draft or cancelled payrolls
                    if (!in_array($payroll->status, ['draft', 'cancelled'])) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $payroll->delete();
                    $deletedCount++;
                }
                
                if ($deletedCount > 0 && $skippedCount > 0) {
                    $this->successMessage = "Deleted {$deletedCount} payroll(s). Skipped {$skippedCount} processed payroll(s).";
                } elseif ($deletedCount > 0) {
                    $this->successMessage = "Successfully deleted {$deletedCount} payroll(s).";
                } else {
                    $this->errorMessage = "Could not delete any payrolls. Only pending or cancelled payrolls can be deleted.";
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
        $payrolls = Payroll::query()
            ->with(['employee.department', 'employee.designation'])
            ->when($this->search, fn($q) => $q->where(function($query) {
                $query->where('payroll_number', 'like', "%{$this->search}%")
                    ->orWhereHas('employee', function($q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                          ->orWhere('last_name', 'like', "%{$this->search}%");
                    });
            }))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->employee, fn($q) => $q->where('employee_id', $this->employee))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $employees = Employee::where('status', 'active')->get();

        return view('livewire.payroll.payroll-table', [
            'payrolls' => $payrolls,
            'employees' => $employees,
        ]);
    }
}
