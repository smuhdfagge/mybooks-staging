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
                // Re-posts only when the journal it would get now differs (COGS
                // missing, say): the old one is reversed and a new one posted, so
                // the balances stay right. It used to delete the old journal's
                // lines without taking them off the balances (audit MB-15).
                $before = Journal::currentFor($invoice);
                $journal = $this->journalService->createInvoiceJournal($invoice);

                if ($journal && $journal->id !== $before?->id) {
                    $updated++;
                    if ($before && $this->hasCogs($journal) && ! $this->hasCogs($before)) {
                        $cogsAdded++;
                    }
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error processing invoice {$invoice->invoice_number}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Journal regeneration completed!');
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

    private function hasCogs(Journal $journal): bool
    {
        return $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '5000'))->exists();
    }
}
