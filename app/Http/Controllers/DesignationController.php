<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index()
    {
        $designations = Designation::with(['department:id,name'])
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(25);

        return view('designations.index', compact('designations'));
    }

    public function create()
    {
        return view('designations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;

        Designation::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('designations.index')->with('success', 'Designation created.');
    }

    public function show(Designation $designation)
    {
        $designation->load('employees');

        return view('designations.show', compact('designation'));
    }

    public function edit(Designation $designation)
    {
        return view('designations.edit', compact('designation'));
    }

    public function update(Request $request, Designation $designation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $designation->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('designations.index')->with('success', 'Designation updated.');
    }

    public function destroy(Designation $designation)
    {
        if ($designation->employees()->exists()) {
            return redirect()->route('designations.index')->with('error', 'Cannot delete designation with employees.');
        }

        $designation->delete();

        return redirect()->route('designations.index')->with('success', 'Designation deleted.');
    }
}
