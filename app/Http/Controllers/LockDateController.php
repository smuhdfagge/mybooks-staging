<?php

namespace App\Http\Controllers;

use App\Actions\LockDates\UpdateLockDates;
use Illuminate\Http\Request;

/**
 * The lock dates card on the accounting periods page (session 11).
 */
class LockDateController extends Controller
{
    public function update(Request $request, UpdateLockDates $update)
    {
        $validated = $request->validate([
            'staff_lock_date' => ['nullable', 'date'],
            'all_users_lock_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $changes = $update->handle(auth()->user()->tenant, $validated, auth()->id());

        return redirect()->route('accounting-periods.index')
            ->with('success', $changes ? 'Lock dates saved.' : 'The lock dates were already set like that.');
    }
}
