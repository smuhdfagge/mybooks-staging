<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\ChecksPermissions;
use App\Livewire\Concerns\ListTable;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Employees list (tables plan T5: the shared list design). */
class EmployeesTable extends Component
{
    use ChecksPermissions, ListTable;

    public string $department = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tab' => ['except' => '', 'as' => 'status'],
        'department' => ['except' => ''],
    ];

    /** Tab => label. "Left" is anyone resigned or terminated. */
    public const LABELS = ['active' => 'Working', 'on-leave' => 'On leave', 'left' => 'Left'];

    protected function sortable(): array
    {
        return ['first_name', 'employee_id', 'hire_date'];
    }

    public function mountListTable(): void
    {
        if (! in_array($this->sortField, $this->sortable(), true)) {
            $this->sortField = 'first_name';
            $this->sortDirection = 'asc';
        }
    }

    protected function rowRelations(): array
    {
        return ['department:id,name', 'designation:id,name'];
    }

    protected function filterProperties(): array
    {
        return ['department'];
    }

    protected function baseQuery(): Builder
    {
        $query = Employee::query();
        if (($term = trim($this->search)) !== '') {
            [$first, $rest] = array_pad(explode(' ', $term, 2), 2, '');
            $query->where(function ($q) use ($term, $first, $rest) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('employee_id', 'like', "%{$term}%");
                if ($rest !== '') {
                    // "Aisha Bello": first and last name together.
                    $q->orWhere(fn ($w) => $w->where('first_name', 'like', "%{$first}%")->where('last_name', 'like', "%{$rest}%"));
                }
            });
        }
        if ($this->department !== '' && ctype_digit($this->department)) {
            $query->where('department_id', (int) $this->department);
        }

        return $query;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active', 'on-leave' => $query->where('status', $tab),
            'left' => $query->whereIn('status', ['terminated', 'resigned']),
            default => $query,
        };
    }

    /** @return array<string, array{label: string, count: int, alert: bool}> one grouped query */
    private function tabs(): array
    {
        $counts = $this->baseQuery()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $tabs = ['' => ['label' => 'All', 'count' => (int) $counts->sum(), 'alert' => false]];
        foreach (self::LABELS as $key => $label) {
            $n = $key === 'left' ? (int) ($counts['terminated'] ?? 0) + (int) ($counts['resigned'] ?? 0) : (int) ($counts[$key] ?? 0);
            $tabs[$key] = ['label' => $label, 'count' => $n, 'alert' => false];
        }

        return $tabs;
    }

    /**
     * Permission required for each bulk action (see ChecksPermissions).
     */
    protected function bulkActionPermissions(): array
    {
        return [
            'activate' => 'edit employees',
            'delete' => 'delete employees',
        ];
    }

    public function applyBulkAction()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (empty($this->selectedItems)) {
            $this->errorMessage = 'Tick at least one employee first.';

            return;
        }

        $this->authorizeBulkAction();

        switch ($this->bulkAction) {
            case 'activate':
                // Someone who has left keeps that status; this is for people back from leave.
                $n = Employee::whereIn('id', $this->selectedItems)->where('status', 'on-leave')->update(['status' => 'active']);
                $this->successMessage = "Marked {$n} employee(s) as back at work. Only people on leave change.";
                break;

            case 'delete':
                $deleted = 0;
                $skipped = 0;
                foreach (Employee::whereIn('id', $this->selectedItems)->get() as $employee) {
                    if ($employee->payrolls()->exists()) {
                        $skipped++;

                        continue;
                    }
                    $employee->delete();
                    $deleted++;
                }
                if ($deleted > 0) {
                    $this->successMessage = "Deleted {$deleted} employee(s).".($skipped ? " Skipped {$skipped} who have been paid through payroll." : '');
                } else {
                    $this->errorMessage = 'None deleted: every ticked employee has been paid through payroll.';
                }
                break;

            default:
                $this->errorMessage = 'Invalid action selected.';

                return;
        }

        $this->selectedItems = [];
        $this->bulkAction = '';
    }

    /** Delete one employee who has never been paid through payroll. */
    public function deleteOne(int $id): void
    {
        $this->requirePermission('delete employees');
        $employee = Employee::findOrFail($id);
        if ($employee->payrolls()->exists()) {
            $this->errorMessage = "{$employee->full_name} has been paid through payroll, so their record is kept. Edit it to mark them as left.";

            return;
        }
        $employee->delete();
        $this->successMessage = "Deleted {$employee->full_name}.";
        $this->selectedItems = array_values(array_diff($this->selectedItems, [(string) $id]));
    }

    public function render()
    {
        return view('livewire.employees.employees-table', [
            'employees' => $this->rows(),
            'tabs' => $this->tabs(),
            'departments' => Department::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'filtered' => $this->isFiltered(),
        ]);
    }
}
