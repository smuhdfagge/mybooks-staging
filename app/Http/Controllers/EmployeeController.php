<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\State;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('employees.index');
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        $designations = Designation::where('is_active', true)->get();
        $employeeId = \App\Support\DocumentNumber::preview(auth()->user()->tenant_id, Employee::class, 'employee_id', 'EMP-', 5);
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $salaryStructures = SalaryStructure::where('is_active', true)->get();

        return view('employees.create', compact('departments', 'designations', 'employeeId', 'countries', 'states', 'salaryStructures'));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $validated['tenant_id'] = $tenantId;
        $validated['employee_id'] = Employee::generateEmployeeId($tenantId);
        $validated['status'] = 'active';

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        }
        unset($validated['photo']);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        abort_unless($employee->tenant_id === auth()->user()->tenant_id, 403);

        $employee->load(['department', 'designation', 'leaves', 'payrolls', 'salaryStructure']);

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        abort_unless($employee->tenant_id === auth()->user()->tenant_id, 403);

        $departments = Department::where('is_active', true)->get();
        $designations = Designation::where('is_active', true)->get();
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $salaryStructures = SalaryStructure::where('is_active', true)->get();

        return view('employees.edit', compact('employee', 'departments', 'designations', 'countries', 'states', 'salaryStructures'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        abort_unless($employee->tenant_id === auth()->user()->tenant_id, 403);

        $validated = $request->validated();

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('employee-photos', 'public');
        }
        unset($validated['photo']);

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        abort_unless($employee->tenant_id === auth()->user()->tenant_id, 403);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }
}
