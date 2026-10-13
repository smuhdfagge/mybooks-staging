<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::withCount('leaves')->orderBy('name')->paginate(25);

        return view('leave-types.index', compact('leaveTypes'));
    }

    public function create()
    {
        return view('leave-types.create', ['leaveType' => new LeaveType(['is_paid' => true, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        LeaveType::create($this->validated($request) + ['tenant_id' => auth()->user()->tenant_id]);

        return redirect()->route('leave-types.index')->with('success', 'Leave type created.');
    }

    public function show(LeaveType $leaveType)
    {
        $leaveType->loadCount('leaves');
        $recentLeaves = $leaveType->leaves()->with('employee')->latest()->limit(10)->get();

        return view('leave-types.show', compact('leaveType', 'recentLeaves'));
    }

    public function edit(LeaveType $leaveType)
    {
        return view('leave-types.edit', compact('leaveType'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $leaveType->update($this->validated($request));

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

    /**
     * The controller used to save "days_allowed", a column that doesn't
     * exist, so the number of days was silently lost (N9). The table column
     * is days_per_year.
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'days_per_year' => 'required|integer|min:0|max:366',
            'max_carry_forward_days' => 'nullable|integer|min:0|max:366',
            'description' => 'nullable|string|max:1000',
        ]);

        return $validated + [
            'is_paid' => $request->boolean('is_paid'),
            'is_carry_forward' => $request->boolean('is_carry_forward'),
            'is_active' => $request->boolean('is_active'),
            'max_carry_forward_days' => (int) ($validated['max_carry_forward_days'] ?? 0),
        ];
    }
}
