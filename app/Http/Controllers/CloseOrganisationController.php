<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * "Close organisation" (finding O7, Nigeria Data Protection Act). Only the
 * owner can close the business. Nothing is erased straight away: the data
 * is erased by tenants:purge-closed 30 days later, and the owner can cancel
 * until then.
 */
class CloseOrganisationController extends Controller
{
    private function ownedTenant(Request $request): Tenant
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant && $tenant->isOwnedBy($request->user()), 403, 'Only the owner of this business can close it.');

        return $tenant;
    }

    public function show(Request $request)
    {
        $tenant = $this->ownedTenant($request);

        return view('settings.close-organisation', ['tenant' => $tenant]);
    }

    public function store(Request $request)
    {
        $tenant = $this->ownedTenant($request);

        if ($tenant->isClosing()) {
            return redirect()->route('settings.close-organisation')->with('error', 'This business is already closing.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_name' => ['required', 'string', function ($attribute, $value, $fail) use ($tenant) {
                if (trim((string) $value) !== trim((string) $tenant->name)) {
                    $fail('Type the business name exactly as shown to confirm.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $purgeAt = now()->addDays(Tenant::CLOSURE_GRACE_DAYS);
        $tenant->forceFill([
            'closure_requested_at' => now(),
            'closure_purge_at' => $purgeAt,
            'closure_requested_by' => $request->user()->id,
        ])->save();

        DataRequest::record('closure', $tenant, $request->user(), [
            'status' => DataRequest::STATUS_SCHEDULED,
            'due_at' => $purgeAt,
            'details' => $request->input('reason'),
        ]);

        return redirect()->route('settings.close-organisation')
            ->with('success', 'Your business will be closed and its data erased on '.$purgeAt->format('j F Y').'. You can cancel until then.');
    }

    public function cancel(Request $request)
    {
        $tenant = $this->ownedTenant($request);

        if (! $tenant->isClosing()) {
            return redirect()->route('settings.close-organisation');
        }

        $tenant->forceFill([
            'closure_requested_at' => null,
            'closure_purge_at' => null,
            'closure_requested_by' => null,
        ])->save();

        DataRequest::where('tenant_id', $tenant->id)
            ->where('type', 'closure')
            ->where('status', DataRequest::STATUS_SCHEDULED)
            ->update(['status' => DataRequest::STATUS_CANCELLED, 'completed_at' => now(), 'handled_by' => $request->user()->name]);

        return redirect()->route('settings.close-organisation')->with('success', 'Closing was cancelled. Nothing will be erased.');
    }
}
