<?php

namespace App\Jobs;

use App\Models\PayrollBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $this->batch->payrolls()
            ->where('status', 'approved')
            ->each(function ($payroll) {
                $payroll->markAsPaid();
            });

        $this->batch->recalculateTotals();
    }
}
