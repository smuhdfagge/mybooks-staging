<?php

namespace App\Actions\AccrualSchedules;

use App\Models\AccrualSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stop a schedule (S9): no more months are released. The months already
 * released stay posted. What is left stays in the prepaid or deferred
 * revenue account, unless the schedule was linked to a bill, expense or
 * invoice and $moveBack is set: then it goes back to the account the
 * setup journal took it from (audit MB-53).
 */
class CancelAccrualSchedule
{
    public function __construct(protected ScheduleSetup $setup) {}

    public function handle(AccrualSchedule $schedule, bool $moveBack = false): AccrualSchedule
    {
        return DB::transaction(function () use ($schedule, $moveBack) {
            // Locked so a release running at the same moment can't slip in.
            $schedule = AccrualSchedule::lockForUpdate()->findOrFail($schedule->id);
            if (! $schedule->isActive()) {
                throw ValidationException::withMessages(['schedule' => 'Only an active schedule can be cancelled.']);
            }
            $schedule->update(['status' => AccrualSchedule::STATUS_CANCELLED, 'cancelled_at' => now()]);

            if ($moveBack) {
                $this->setup->undo($schedule, $schedule->remaining(), 'Schedule cancelled, rest moved back');
            }

            return $schedule;
        });
    }
}
