<?php

namespace App\Console\Commands;

use App\Actions\Accruals\ReleaseAccrualSchedule;
use App\Models\AccrualSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Releases the months that are due on every active prepaid expense and
 * deferred revenue schedule, for every business. Safe to run again.
 */
class ReleaseAccrualSchedules extends Command
{
    protected $signature = 'accruals:release {--date= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Release the months that are due on prepaid expense and deferred revenue schedules';

    public function handle(ReleaseAccrualSchedule $release): int
    {
        $asOf = $this->option('date') ? now()->parse($this->option('date')) : now();
        $ids = AccrualSchedule::withoutGlobalScopes()
            ->where('status', AccrualSchedule::STATUS_ACTIVE)
            ->where('start_date', '<', $asOf->copy()->addDay()->toDateString())
            ->pluck('id');

        $released = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $schedule = AccrualSchedule::withoutGlobalScopes()->find($id);
            try {
                $released += $release->handle($schedule, $asOf);
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Accrual schedule release failed', ['schedule_id' => $id, 'tenant_id' => $schedule?->tenant_id, 'error' => $e->getMessage()]);
                $this->error("Schedule #{$id}: {$e->getMessage()}");
            }
        }

        $this->info("Months released: {$released}".($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
