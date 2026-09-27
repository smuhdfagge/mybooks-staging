<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Console\Command;

class RegenerateInvoiceJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journals:regenerate-invoices 
                            {--tenant= : Specific tenant ID (optional)}
                            {--invoice= : Specific invoice ID (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate journal entries for invoices to include Cost of Goods Sold (COGS)';

    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        parent::__construct();
        $this->journalService = $journalService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $invoiceId = $this->option('invoice');

        $query = Invoice::withoutGlobalScopes()
            ->with(['items.item', 'customer']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->warn('No invoices found to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$invoices->count()} invoices...");
        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        $updated = 0;
        $cogsAdded = 0;

        foreach ($invoices as $invoice) {
            try {
                // Delete existing journal for this invoice
                $existingJournal = Journal::where('reference_type', Invoice::class)
                    ->where('reference_id', $invoice->id)
                    ->first();

                if ($existingJournal) {
                    // Check if COGS entry already exists
                    $hasCogs = $existingJournal->entries()
                        ->whereHas('account', function ($q) {
                            $q->where('account_code', '5000');
                        })
                        ->exists();

                    if (!$hasCogs) {
                        // Regenerate the journal to include COGS
                        $existingJournal->entries()->delete();
                        $existingJournal->delete();
                        
                        $this->journalService->createInvoiceJournal($invoice);
                        $updated++;

                        // Check if COGS was added
                        $newJournal = Journal::where('reference_type', Invoice::class)
                            ->where('reference_id', $invoice->id)
                            ->first();
                        
                        if ($newJournal) {
                            $newHasCogs = $newJournal->entries()
                                ->whereHas('account', function ($q) {
                                    $q->where('account_code', '5000');
                                })
                                ->exists();
                            
                            if ($newHasCogs) {
                                $cogsAdded++;
                            }
                        }
                    }
                } else {
                    // Create new journal
                    $this->journalService->createInvoiceJournal($invoice);
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing invoice {$invoice->invoice_number}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Journal regeneration completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Invoices Processed', $invoices->count()],
                ['Journals Updated', $updated],
                ['COGS Entries Added', $cogsAdded],
            ]
        );

        return Command::SUCCESS;
    }
}
