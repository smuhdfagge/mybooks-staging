<?php

namespace App\Services\BankStatements;

use App\Models\Bank;
use App\Models\BankStatementLine;
use App\Models\BankTransaction;
use App\Services\BankFeeds\Matcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves an imported bank statement and matches its lines to the lines from
 * the books (audit MB-08). Matching suggests; nothing is reconciled until
 * the person ticks and submits on the reconcile screen.
 *
 * A statement line matches a line from the books that is not reconciled,
 * goes the same way, has exactly the same amount and is dated within
 * WINDOW_DAYS. Best score first (the bank feed Matcher's date and text
 * scores); each line on either side is used once.
 */
class StatementImport
{
    public const WINDOW_DAYS = 3;

    public function __construct(private Matcher $matcher) {}

    /**
     * Saves the lines; a line already imported for this bank is skipped, so
     * uploading the same statement twice adds nothing.
     *
     * @param  list<array{date: string, description: ?string, reference: ?string, amount: float, balance: ?float}>  $lines
     * @return array{added: int, skipped: int}
     */
    public function save(Bank $bank, array $lines, ?int $userId): array
    {
        // Identical rows in one file are distinct transactions (two equal
        // transfers on a day): number them so both are kept.
        $seen = [];
        $rows = [];
        foreach ($lines as $line) {
            $base = implode('|', [$line['date'], number_format($line['amount'], 2, '.', ''), mb_strtolower((string) $line['description']), mb_strtolower((string) $line['reference'])]);
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $rows[hash('sha256', $bank->id.'|'.$base.'|'.$seen[$base])] = $line;
        }

        return DB::transaction(function () use ($bank, $rows, $userId) {
            $existing = BankStatementLine::withoutGlobalScopes()->where('bank_id', $bank->id)
                ->whereIn('line_hash', array_keys($rows))->pluck('line_hash')->flip();
            $added = 0;
            foreach ($rows as $hash => $line) {
                if (isset($existing[$hash])) {
                    continue;
                }
                BankStatementLine::create([
                    'tenant_id' => $bank->tenant_id,
                    'bank_id' => $bank->id,
                    'line_hash' => $hash,
                    'date' => $line['date'],
                    'description' => $line['description'],
                    'reference' => $line['reference'],
                    'amount' => $line['amount'],
                    'balance' => $line['balance'],
                    'imported_by' => $userId,
                ]);
                $added++;
            }

            return ['added' => $added, 'skipped' => count($rows) - $added];
        });
    }

    /** Matches the bank's unmatched statement lines. Returns how many were matched. */
    public function match(Bank $bank): int
    {
        $statement = BankStatementLine::withoutGlobalScopes()->where('bank_id', $bank->id)
            ->whereNull('bank_transaction_id')->orderBy('date')->orderBy('id')->get();
        if ($statement->isEmpty()) {
            return 0;
        }

        $taken = BankStatementLine::withoutGlobalScopes()->where('bank_id', $bank->id)
            ->whereNotNull('bank_transaction_id')->pluck('bank_transaction_id')->all();
        $from = CarbonImmutable::parse($statement->min('date'))->subDays(self::WINDOW_DAYS)->toDateString();
        // "Before the next day": SQLite keeps a time part on dates.
        $before = CarbonImmutable::parse($statement->max('date'))->addDays(self::WINDOW_DAYS + 1)->toDateString();
        $books = BankTransaction::withoutGlobalScopes()->where('bank_id', $bank->id)
            ->where('is_reconciled', false)->whereNotIn('id', $taken)
            ->where('date', '>=', $from)->where('date', '<', $before)
            ->get();

        $scored = [];
        foreach ($statement as $line) {
            foreach ($books as $book) {
                if (($score = $this->score($line, $book)) !== null) {
                    $scored[] = [$score, $line, $book];
                }
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1]->id <=> $b[1]->id ?: $a[2]->id <=> $b[2]->id);

        $usedLines = [];
        $usedBooks = [];
        foreach ($scored as [, $line, $book]) {
            if (isset($usedLines[$line->id]) || isset($usedBooks[$book->id])) {
                continue;
            }
            $line->forceFill(['bank_transaction_id' => $book->id])->save();
            $usedLines[$line->id] = $usedBooks[$book->id] = true;
        }

        return count($usedLines);
    }

    /** Null when the two can't be the same transaction. */
    private function score(BankStatementLine $line, BankTransaction $book): ?int
    {
        if ($line->isInflow() !== $book->isInflow() || abs(abs((float) $line->amount) - (float) $book->amount) >= 0.005) {
            return null;
        }
        $days = (int) abs($line->date->startOfDay()->diffInDays($book->date->startOfDay(), false));
        if ($days > self::WINDOW_DAYS) {
            return null;
        }
        $narration = trim($line->description.' '.$line->reference);

        return min(100, 50 + Matcher::dateScore($days) + $this->matcher->textScore($narration, array_filter([(string) $book->reference]), $book->payee ?: $book->description));
    }

    /**
     * Statement lines still to deal with: not matched, or matched to a line
     * not reconciled yet.
     *
     * @return Collection<int, BankStatementLine>
     */
    public function open(Bank $bank): Collection
    {
        return BankStatementLine::withoutGlobalScopes()->where('bank_id', $bank->id)
            ->with(['bankTransaction' => fn ($q) => $q->withoutGlobalScopes()])
            ->where(fn ($q) => $q->whereNull('bank_transaction_id')
                ->orWhereHas('bankTransaction', fn ($t) => $t->withoutGlobalScopes()->where('is_reconciled', false)))
            ->orderBy('date')->orderBy('id')
            ->get();
    }

    /** Removes the imported lines not done yet (a wrong file); reconciled ones stay. */
    public function discard(Bank $bank): int
    {
        $lines = $this->open($bank);
        BankStatementLine::withoutGlobalScopes()->whereKey($lines->modelKeys())->delete();

        return $lines->count();
    }
}
