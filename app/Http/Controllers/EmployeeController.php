<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        $employeeId = Employee::generateEmployeeId(auth()->user()->tenant_id);
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();
        $salaryStructures = SalaryStructure::where('is_active', true)->get();

        return view('employees.create', compact('departments', 'designations', 'employeeId', 'countries', 'states', 'salaryStructures'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'hire_date' => 'required|date',
            'employment_type' => 'required|in:full-time,part-time,contract,intern',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:monthly,hourly,annual',
            'salary_structure_id' => ['nullable', Rule::exists('salary_structures', 'id')->where('tenant_id', $tenantId)],
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_routing_number' => 'nullable|string|max:50',
            'tax_id' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

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

    public function update(Request $request, Employee $employee)
    {
        abort_unless($employee->tenant_id === auth()->user()->tenant_id, 403);

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'hire_date' => 'required|date',
            'termination_date' => 'nullable|date|after:hire_date',
            'employment_type' => 'required|in:full-time,part-time,contract,intern',
            'salary' => 'nullable|numeric|min:0',
            'salary_type' => 'nullable|in:monthly,hourly,annual',
            'salary_structure_id' => ['nullable', Rule::exists('salary_structures', 'id')->where('tenant_id', $tenantId)],
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_routing_number' => 'nullable|string|max:50',
            'tax_id' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,on-leave,terminated,resigned',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

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
