<?php

namespace App\Actions\AccrualSchedules;

use App\Models\AccrualSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stop a schedule (S9): no more months are released. The months already
 * released stay posted, and what is left stays in the prepaid or deferred
 * revenue account; move it with a journal if needed.
 */
class CancelAccrualSchedule
{
    public function handle(AccrualSchedule $schedule): AccrualSchedule
    {
        return DB::transaction(function () use ($schedule) {
            // Locked so a release running at the same moment can't slip in.
            $schedule = AccrualSchedule::lockForUpdate()->findOrFail($schedule->id);
            if (! $schedule->isActive()) {
                throw ValidationException::withMessages(['schedule' => 'Only an active schedule can be cancelled.']);
            }
            $schedule->update(['status' => AccrualSchedule::STATUS_CANCELLED, 'cancelled_at' => now()]);

            return $schedule;
        });
    }
}
