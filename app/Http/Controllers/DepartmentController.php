<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['parent', 'manager', 'employees'])
            ->withCount('employees')
            ->latest()
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        $employees = Employee::where('status', 'active')->get();

        return view('departments.create', compact('departments', 'employees'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'parent_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ]);

        $validated['tenant_id'] = $tenantId;
        Department::create($validated);

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load(['parent', 'children', 'manager', 'employees', 'designations']);

        return view('departments.show', compact('department'));
    }

    public function edit(Department $department)
    {
        $departments = Department::where('is_active', true)->where('id', '!=', $department->id)->get();
        $employees = Employee::where('status', 'active')->get();

        return view('departments.edit', compact('department', 'departments', 'employees'));
    }

    public function update(Request $request, Department $department)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'parent_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId), Rule::notIn($this->selfAndDescendants($department))],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_active' => 'boolean',
        ], [
            'parent_id.not_in' => 'A department cannot sit under itself or one of its own sub-departments.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', $department->is_active);
        $department->update($validated);

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete department with employees.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }

    /** IDs of $department and everything below it, to stop parent loops. */
    private function selfAndDescendants(Department $department): array
    {
        $ids = [$department->id];
        $level = [$department->id];
        while ($level) {
            $level = Department::whereIn('parent_id', $level)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }
}
