<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Log of data-protection requests (finding O7). Closing a business is
 * recorded automatically; requests that arrive by email or phone are
 * logged here, and marked done when answered.
 */
class AdminDataRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = DataRequest::query()
            ->when($request->query('status') === 'open', fn ($q) => $q->whereIn('status', [DataRequest::STATUS_RECEIVED, DataRequest::STATUS_SCHEDULED]))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.data-requests.index', [
            'requests' => $requests,
            'types' => DataRequest::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(DataRequest::TYPES))],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'requester' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = isset($validated['tenant_id']) ? Tenant::find($validated['tenant_id']) : null;

        DataRequest::create([
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
            'requester' => $validated['requester'],
            'type' => $validated['type'],
            'status' => DataRequest::STATUS_RECEIVED,
            'details' => $validated['details'] ?? null,
            'due_at' => now()->addDays(DataRequest::RESPONSE_DAYS),
            'handled_by' => auth('admin')->user()?->name,
        ]);

        return redirect()->route('admin.data-requests.index')->with('success', 'Request logged.');
    }

    public function complete(DataRequest $dataRequest)
    {
        if ($dataRequest->isOpen()) {
            $dataRequest->update([
                'status' => DataRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'handled_by' => auth('admin')->user()?->name,
            ]);
        }

        return redirect()->route('admin.data-requests.index')->with('success', 'Request marked as done.');
    }
}
