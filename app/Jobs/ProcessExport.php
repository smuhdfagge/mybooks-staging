<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsAsUser;
use App\Models\Export;
use App\Services\ActivityLogService;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Builds an export or backup file in the background (P3). The export
 * record can be downloaded from the Exports page once it is completed.
 */
class ProcessExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsUser, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public Export $export
    ) {}

    public function handle(ExportService $service): void
    {
        $export = Export::withoutGlobalScopes()->find($this->export->id);
        if (! $export || $export->status !== Export::STATUS_PENDING) {
            return; // deleted, or already handled
        }

        $this->runAsUser($export->user_id, function () use ($service, $export) {
            if (! $service->processExport($export)) {
                return;
            }

            if ($export->type === Export::TYPE_FULL_BACKUP) {
                ActivityLogService::logBackup($export->included_data ?? [], $export->format);
            } else {
                ActivityLogService::logExport($export->type, ['format' => $export->format]);
            }
        });
    }

    public function failed(?Throwable $e): void
    {
        Export::withoutGlobalScopes()->whereKey($this->export->id)->update([
            'status' => Export::STATUS_FAILED,
            'error_message' => mb_substr($e?->getMessage() ?? 'The background job stopped.', 0, 1000),
            'completed_at' => now(),
        ]);
    }
}
