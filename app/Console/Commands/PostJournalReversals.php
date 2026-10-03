<?php

namespace App\Console\Commands;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Posts the automatic reversal of every posted journal whose "reverse on"
 * date has come (S8, accruals), for every business. Safe to run again: a
 * journal is only reversed once. One journal failing doesn't stop the rest.
 */
class PostJournalReversals extends Command
{
    protected $signature = 'journals:post-reversals {--date= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Post automatic reversals of journals whose "reverse on" date has come';

    public function handle(JournalService $journals): int
    {
        if (! EnsureFeatureEnabled::enabled('auto_reversing_journals')) {
            $this->info('Automatic journal reversals are switched off (mybooks.features.auto_reversing_journals).');

            return self::SUCCESS;
        }

        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today();

        // All businesses (nobody is signed in). "Before tomorrow" rather than
        // "<= today": SQLite keeps a time part on dates.
        $due = Journal::withoutGlobalScope('tenant')
            ->whereNotNull('reverse_on')
            ->whereNull('auto_reversal_journal_id')
            ->where('is_posted', true)
            ->where('status', 'posted')
            ->where('reverse_on', '<', $today->copy()->addDay()->toDateString())
            ->orderBy('reverse_on')->orderBy('id')
            ->get(['id', 'tenant_id', 'journal_number']);

        $posted = 0;
        $failed = 0;
        foreach ($due as $journal) {
            try {
                $reversal = $journals->postAutoReversal($journal, $today);
                if ($reversal) {
                    $posted++;
                    $this->line("Business #{$journal->tenant_id}: {$journal->journal_number} reversed by {$reversal->journal_number} on {$reversal->journal_date->toDateString()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Automatic journal reversal failed', [
                    'journal_id' => $journal->id, 'tenant_id' => $journal->tenant_id, 'error' => $e->getMessage(),
                ]);
                $this->error("Journal {$journal->journal_number} (business #{$journal->tenant_id}): {$e->getMessage()}");
            }
        }

        $this->info("Reversals posted: {$posted}".($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
