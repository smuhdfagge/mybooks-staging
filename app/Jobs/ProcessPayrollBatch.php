<?php

namespace App\Jobs;

use App\Models\PayrollBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pays every approved payroll in a large batch (finding N7).
 *
 * The batch is "processing" while queued. It becomes "paid" only when every
 * payroll has been paid and journalled; if anything fails, all of it is
 * rolled back and the batch is "failed" so it can be tried again.
 */
class ProcessPayrollBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public PayrollBatch $batch
    ) {}

    public function handle(): void
    {
        $batch = PayrollBatch::withoutGlobalScopes()->findOrFail($this->batch->id);

        if ($batch->status !== PayrollBatch::STATUS_PROCESSING) {
            return; // already handled, or cancelled meanwhile
        }

        DB::transaction(function () use ($batch) {
            $batch->payrolls()
                ->where('status', 'approved')
                ->each(fn ($payroll) => $payroll->markAsPaid());

            $batch->update([
                'status' => PayrollBatch::STATUS_PAID,
                'paid_at' => now(),
                'failure_reason' => null,
            ]);
            $batch->recalculateTotals();
        });
    }

    public function failed(?Throwable $e): void
    {
        PayrollBatch::withoutGlobalScopes()->whereKey($this->batch->id)->update([
            'status' => PayrollBatch::STATUS_FAILED,
            'paid_at' => null,
            'failure_reason' => mb_substr($e?->getMessage() ?? 'The background job stopped.', 0, 1000),
        ]);
    }
}
