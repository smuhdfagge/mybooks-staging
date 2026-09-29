<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends BaseApiController
{
    /**
     * Get all employees
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'designation']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by department
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Filter by designation
        if ($designationId = $request->input('designation_id')) {
            $query->where('designation_id', $designationId);
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['first_name', 'last_name', 'email', 'employee_id', 'status', 'hire_date', 'created_at', 'updated_at'],
            'first_name'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $employees = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($employees->through(fn ($employee) => new EmployeeResource($employee)));
    }

    /**
     * Get a specific employee
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['department', 'designation']);

        return $this->success(new EmployeeResource($employee));
    }

    /**
     * Create a new employee
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        }
        unset($validated['photo']);

        $validated['tenant_id'] = $this->getTenantId();

        // Generate employee ID if not provided
        if (empty($validated['employee_id'])) {
            $validated['employee_id'] = Employee::generateEmployeeId($validated['tenant_id']);
        }

        $employee = Employee::create($validated);
        $employee->load(['department', 'designation']);

        return $this->created(new EmployeeResource($employee), 'Employee created successfully');
    }

    /**
     * Update an employee
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $validated = $request->validated();
        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        }
        unset($validated['photo']);

        $employee->update($validated);
        $employee->load(['department', 'designation']);

        return $this->success(new EmployeeResource($employee), 'Employee updated successfully');
    }

    /**
     * Delete an employee
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return $this->success(null, 'Employee deleted successfully');
    }

    /**
     * Get employee summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalEmployees = Employee::where('tenant_id', $tenantId)->count();
        $activeEmployees = Employee::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $totalSalary = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->sum('salary');

        return $this->success([
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $totalEmployees - $activeEmployees,
            'total_monthly_salary' => (float) $totalSalary,
            'by_status' => [
                'active' => $activeEmployees,
                'inactive' => Employee::where('tenant_id', $tenantId)->where('status', 'inactive')->count(),
                'terminated' => Employee::where('tenant_id', $tenantId)->where('status', 'terminated')->count(),
            ],
        ]);
    }
}
