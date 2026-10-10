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
        $open = [DataRequest::STATUS_RECEIVED, DataRequest::STATUS_SCHEDULED];
        $status = in_array($request->query('status'), ['open', DataRequest::STATUS_COMPLETED, DataRequest::STATUS_CANCELLED], true) ? (string) $request->query('status') : '';

        $requests = DataRequest::query()
            ->when($status === 'open', fn ($q) => $q->whereIn('status', $open))
            ->when(in_array($status, [DataRequest::STATUS_COMPLETED, DataRequest::STATUS_CANCELLED], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = DataRequest::query()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $late = DataRequest::query()->whereIn('status', $open)->where('due_at', '<', now())->count();
        $url = fn (string $key) => route('admin.data-requests.index', $key === '' ? [] : ['status' => $key]);

        return view('admin.data-requests.index', [
            'requests' => $requests,
            'types' => DataRequest::TYPES,
            'status' => $status,
            'tabs' => [
                '' => ['label' => 'All', 'count' => (int) $counts->sum(), 'href' => $url('')],
                'open' => ['label' => 'To answer', 'count' => (int) $counts->only($open)->sum(), 'alert' => $late > 0, 'href' => $url('open')],
                'completed' => ['label' => 'Done', 'count' => (int) ($counts[DataRequest::STATUS_COMPLETED] ?? 0), 'href' => $url('completed')],
                'cancelled' => ['label' => 'Cancelled', 'count' => (int) ($counts[DataRequest::STATUS_CANCELLED] ?? 0), 'href' => $url('cancelled')],
            ],
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
