<?php

namespace App\Actions\AccrualSchedules;

use App\Models\AccrualSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delete a schedule (S9), only while no month has been released (it has
 * posted nothing). Once a month is released, cancel it instead.
 */
class DeleteAccrualSchedule
{
    public function handle(AccrualSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $schedule = AccrualSchedule::lockForUpdate()->findOrFail($schedule->id);
            if ($schedule->hasReleases()) {
                throw ValidationException::withMessages(['schedule' => 'Months have already been released from this schedule, so it can\'t be deleted. Cancel it instead to stop the months still to come.']);
            }
            $schedule->delete();
        });
    }
}
