<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPayrollJournals extends Command
{
    protected $signature = 'payroll:fix-journals {tenant_id}';
    protected $description = 'Regenerate journal entries for paid payroll records';

    public function handle(JournalService $journalService): int
    {
        $tenantId = (int) $this->argument('tenant_id');

        $paidPayrolls = Payroll::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->with('employee')
            ->get();

        $this->info("Found {$paidPayrolls->count()} paid payroll records for tenant {$tenantId}.");

        if ($paidPayrolls->isEmpty()) {
            $this->warn('No paid payroll records to fix.');
            return 0;
        }

        $fixed = 0;
        $created = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($paidPayrolls->count());
        $bar->start();

        foreach ($paidPayrolls as $payroll) {
            try {
                DB::transaction(function () use ($payroll, $journalService, &$fixed, &$created, $tenantId) {
                    $existingJournal = Journal::withoutGlobalScopes()
                        ->where('reference_type', Payroll::class)
                        ->where('reference_id', $payroll->id)
                        ->first();

                    if ($existingJournal) {
                        $journalService->deleteJournalForTransaction(Payroll::class, $payroll->id, $tenantId);
                        $fixed++;
                    } else {
                        $created++;
                    }

                    $journalService->createPayrollJournal($payroll);
                });
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error on {$payroll->payroll_number}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done! Fixed {$fixed} existing journals, created {$created} missing journals. Errors: {$errors}.");

        return 0;
    }
}
