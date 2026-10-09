<?php

namespace App\Services\BankFeeds;

use App\Enums\BankFeedLineStatus;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The bank feed as the statement in bank reconciliation (session 17): the
 * bank's own balance against the books, the bank lines still unmatched, and
 * the records in the books the bank has not shown yet.
 */
class FeedStatement
{
    public function __construct(private Matcher $matcher) {}

    /**
     * @return array{
     *     connection: BankFeedConnection,
     *     feed_balance: ?float,
     *     feed_balance_at: mixed,
     *     book_balance: float,
     *     difference: ?float,
     *     unmatched_count: int,
     *     unmatched_in: float,
     *     unmatched_out: float,
     *     unmatched_lines: Collection<int, BankFeedLine>,
     *     books_without_line: Collection<int, Candidate>,
     * }|null
     */
    public function forBank(Bank $bank): ?array
    {
        $connection = BankFeedConnection::counted()->where('bank_id', $bank->id)->first();
        if (! $connection) {
            return null;
        }

        $new = BankFeedLine::where('bank_id', $bank->id)->where('status', BankFeedLineStatus::New->value);
        $feedBalance = $connection->providerBalance();
        $book = (float) $bank->current_balance;

        // Books are compared from a few days before the first line the bank gave us.
        $first = BankFeedLine::where('bank_id', $bank->id)->min('date');
        $from = $first ? CarbonImmutable::parse($first)->subDays((int) config('mybooks.bank_feeds.match_window_days', 5)) : CarbonImmutable::now()->subDays(90);

        return [
            'connection' => $connection,
            'feed_balance' => $feedBalance,
            'feed_balance_at' => $connection->balance_at,
            'book_balance' => $book,
            'difference' => $feedBalance === null ? null : round($feedBalance - $book, 2),
            'unmatched_count' => (clone $new)->count(),
            'unmatched_in' => (float) (clone $new)->where('direction', 'credit')->sum('amount'),
            'unmatched_out' => (float) (clone $new)->where('direction', 'debit')->sum('amount'),
            'unmatched_lines' => (clone $new)->orderByDesc('date')->limit(20)->get(),
            'books_without_line' => $this->matcher->booksWithoutLine($bank, $from, 50),
        ];
    }
}
