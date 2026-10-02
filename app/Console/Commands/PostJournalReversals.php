<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Posts the automatic reversal of every posted journal whose "reverse on"
 * date has come (accruals), for every business. Safe to run again: a
 * journal is only reversed once. A reversal due in a closed or locked
 * period is posted on the first open date, and its description says so.
 */
class PostJournalReversals extends Command
{
    protected $signature = 'journals:post-reversals {--date= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Post automatic reversals of journals whose "reverse on" date has come';

    public function handle(JournalService $journals): int
    {
        $today = $this->option('date') ? now()->parse($this->option('date')) : now();
        // "Before tomorrow" rather than "<= today": SQLite keeps a time part on dates.
        $due = Journal::withoutGlobalScopes()
            ->whereNotNull('reverse_on')
            ->whereNull('auto_reversal_journal_id')
            ->where('is_posted', true)
            ->where('status', 'posted')
            ->where('reverse_on', '<', $today->copy()->addDay()->toDateString())
            ->orderBy('reverse_on')->orderBy('id')
            ->pluck('id');

        $posted = 0;
        $failed = 0;
        foreach ($due as $id) {
            $journal = Journal::withoutGlobalScopes()->find($id);
            try {
                $reversal = $journals->postAutoReversal($journal);
                if ($reversal) {
                    $posted++;
                    $this->line("Tenant #{$journal->tenant_id}: {$journal->journal_number} reversed by {$reversal->journal_number} on {$reversal->journal_date->toDateString()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Automatic journal reversal failed', ['journal_id' => $id, 'tenant_id' => $journal?->tenant_id, 'error' => $e->getMessage()]);
                $this->error("Journal #{$id}: {$e->getMessage()}");
            }
        }

        $this->info("Reversals posted: {$posted}".($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
