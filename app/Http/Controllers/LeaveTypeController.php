<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return view('leave-types.index');
    }

    public function create()
    {
        return view('leave-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'days_allowed' => 'required|integer|min:0',
            'is_paid' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;
        
        LeaveType::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'days_allowed' => $validated['days_allowed'],
            'is_paid' => $validated['is_paid'] ?? true,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('leave-types.index')->with('success', 'Leave type created.');
    }

    public function show(LeaveType $leaveType)
    {
        return view('leave-types.show', compact('leaveType'));
    }

    public function edit(LeaveType $leaveType)
    {
        return view('leave-types.edit', compact('leaveType'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'days_allowed' => 'required|integer|min:0',
            'is_paid' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $leaveType->update([
            'name' => $validated['name'],
            'days_allowed' => $validated['days_allowed'],
            'is_paid' => $validated['is_paid'] ?? true,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('leave-types.index')->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->leaves()->exists()) {
            return redirect()->route('leave-types.index')->with('error', 'Cannot delete leave type with existing leave records.');
        }

        $leaveType->delete();
        return redirect()->route('leave-types.index')->with('success', 'Leave type deleted.');
    }
}
