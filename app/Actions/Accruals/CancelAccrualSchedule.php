<?php

namespace App\Actions\Accruals;

use App\Models\AccrualSchedule;
use Illuminate\Validation\ValidationException;

/**
 * Stop a schedule: no more months are released. What has been released
 * stays posted, and what is left stays in the prepaid or deferred account
 * (move it with a journal if needed).
 */
class CancelAccrualSchedule
{
    public function handle(AccrualSchedule $schedule): AccrualSchedule
    {
        if ($schedule->status !== AccrualSchedule::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['schedule' => 'Only an active schedule can be stopped.']);
        }

        $schedule->update(['status' => AccrualSchedule::STATUS_CANCELLED, 'cancelled_at' => now()]);

        return $schedule;
    }
}
