<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index()
    {
        return view('leaves.index');
    }

    public function create()
    {
        $employees = Employee::where('status', 'active')->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();
        return view('leaves.create', compact('employees', 'leaveTypes'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);
        
        // Calculate days
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $days = $startDate->diffInDays($endDate) + 1;
        
        $leave = Leave::create([
            'tenant_id' => $tenantId,
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
            'applied_by' => auth()->id(),
        ]);

        return redirect()->route('leaves.show', $leave)->with('success', 'Leave request submitted.');
    }

    public function show(Leave $leave)
    {
        $leave->load(['employee', 'leaveType', 'approvedBy']);
        return view('leaves.show', compact('leave'));
    }

    public function edit(Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return redirect()->route('leaves.show', $leave)->with('error', 'Only pending leaves can be edited.');
        }

        $employees = Employee::where('status', 'active')->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();
        return view('leaves.edit', compact('leave', 'employees', 'leaveTypes'));
    }

    public function update(Request $request, Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return redirect()->route('leaves.show', $leave)->with('error', 'Only pending leaves can be updated.');
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $days = $startDate->diffInDays($endDate) + 1;

        $leave->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'reason' => $validated['reason'] ?? null,
        ]);

        return redirect()->route('leaves.show', $leave)->with('success', 'Leave request updated.');
    }

    public function destroy(Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return redirect()->route('leaves.index')->with('error', 'Only pending leaves can be deleted.');
        }

        $leave->delete();
        return redirect()->route('leaves.index')->with('success', 'Leave request deleted.');
    }

    public function approve(Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return redirect()->route('leaves.show', $leave)->with('error', 'Leave already processed.');
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Log the activity
        $leave->logCustomActivity(ActivityLog::ACTION_APPROVED, "Leave request for {$leave->employee->first_name} {$leave->employee->last_name} was approved");

        return redirect()->route('leaves.show', $leave)->with('success', 'Leave approved.');
    }

    public function reject(Request $request, Leave $leave)
    {
        if ($leave->status !== 'pending') {
            return redirect()->route('leaves.show', $leave)->with('error', 'Leave already processed.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        // Log the activity
        $leave->logCustomActivity(ActivityLog::ACTION_REJECTED, "Leave request for {$leave->employee->first_name} {$leave->employee->last_name} was rejected");

        return redirect()->route('leaves.show', $leave)->with('success', 'Leave rejected.');
    }
}
