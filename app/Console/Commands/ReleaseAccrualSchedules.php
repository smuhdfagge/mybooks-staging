<?php

namespace App\Console\Commands;

use App\Actions\AccrualSchedules\ReleaseAccrualSchedule;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\AccrualSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Releases the months that are due on every active prepaid expense and
 * deferred revenue schedule, for every business (S9). Safe to run again:
 * each month is released once. One schedule failing doesn't stop the rest.
 */
class ReleaseAccrualSchedules extends Command
{
    protected $signature = 'accruals:release {--date= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Release the months that are due on prepaid expense and deferred revenue schedules';

    public function handle(ReleaseAccrualSchedule $release): int
    {
        if (! EnsureFeatureEnabled::enabled('prepaid_schedules')) {
            $this->info('Prepaid and deferred revenue schedules are switched off (mybooks.features.prepaid_schedules).');

            return self::SUCCESS;
        }

        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today();

        // All businesses (nobody is signed in). A schedule can have a month
        // due once its start month has ended; release() checks the rest.
        $schedules = AccrualSchedule::withoutGlobalScope('tenant')
            ->where('status', AccrualSchedule::STATUS_ACTIVE)
            ->where('start_date', '<', $today->copy()->addDay()->toDateString())
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'schedule_number']);

        $released = 0;
        $failed = 0;
        foreach ($schedules as $schedule) {
            try {
                $count = $release->handle($schedule, $today);
                if ($count) {
                    $released += $count;
                    $this->line("Business #{$schedule->tenant_id}: {$schedule->schedule_number} released {$count} month(s)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Prepaid/deferred schedule release failed', [
                    'schedule_id' => $schedule->id, 'tenant_id' => $schedule->tenant_id, 'error' => $e->getMessage(),
                ]);
                $this->error("Schedule {$schedule->schedule_number} (business #{$schedule->tenant_id}): {$e->getMessage()}");
            }
        }

        $this->info("Months released: {$released}".($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
