<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsAsUser;
use App\Models\Import;
use App\Services\ActivityLogService;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs an import in the background (P3). The import record shows its
 * status and progress while this runs.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsUser, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public Import $import
    ) {}

    public function handle(ImportService $service): void
    {
        $import = Import::withoutGlobalScopes()->find($this->import->id);
        if (! $import || $import->status !== Import::STATUS_PROCESSING) {
            return; // deleted, or already handled
        }

        $this->runAsUser($import->user_id, function () use ($service, $import) {
            $service->processImport($import);

            ActivityLogService::log(
                'import',
                "Imported {$import->type}: {$import->successful_rows} successful, {$import->failed_rows} failed",
                Import::class,
                $import->id,
                $import->original_filename,
                [
                    'type' => $import->type,
                    'total_rows' => $import->total_rows,
                    'successful' => $import->successful_rows,
                    'failed' => $import->failed_rows,
                ]
            );
        });
    }

    public function failed(?Throwable $e): void
    {
        Import::withoutGlobalScopes()->whereKey($this->import->id)->update([
            'status' => Import::STATUS_FAILED,
            'error_message' => mb_substr($e?->getMessage() ?? 'The background job stopped.', 0, 1000),
            'completed_at' => now(),
        ]);
    }
}
