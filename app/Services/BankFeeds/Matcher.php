<?php

namespace App\Services\BankFeeds;

use App\Models\Bank;
use App\Models\BankFeedLine;
use App\Models\ChartOfAccount;
use App\Models\CreditNoteRefund;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\StatutoryRemittance;
use App\Models\VendorCreditRefund;
use App\Services\AccountCodeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggests which MyBooks record a bank line is (session 17). Nothing here
 * changes data: accepting is a click (LineActions).
 *
 * A record is a candidate when it is on the same bank account, has exactly
 * the line's amount (required), is dated within match_window_days of the
 * line, is not already linked to another line, and was not refused with
 * "Not this one". Score out of 100: 50 for the amount, up to 25 for how
 * close the dates are, up to 25 for the reference or the name showing up in
 * the bank's narration. One record is never suggested for two lines in the
 * same batch (best score first).
 *
 * Records considered: payments received and supplier credit refunds (money
 * in); payments made, paid expenses, tax remittances and customer refunds
 * (money out); and transfers posted from a line on another account.
 */
class Matcher
{
    /** Words that say nothing about who a payment was to or from. */
    private const NOISE = ['ltd', 'limited', 'plc', 'nig', 'nigeria', 'enterprises', 'enterprise', 'company', 'co', 'and', 'the', 'trading', 'services', 'global', 'investment', 'investments', 'ventures', 'stores', 'store', 'transfer', 'payment', 'from', 'for', 'trf', 'nip', 'ussd', 'web', 'mobile'];

    /**
     * Best suggestion per line id, with no record used twice.
     *
     * @param  iterable<BankFeedLine>  $lines
     * @return array<int, Suggestion>
     */
    public function suggest(iterable $lines): array
    {
        $lines = collect($lines)->filter(fn (BankFeedLine $l) => $l->isNew())->values();
        $window = (int) config('mybooks.bank_feeds.match_window_days', 5);

        $scored = [];
        foreach ($lines->groupBy(fn (BankFeedLine $l) => $l->tenant_id.'|'.$l->bank_id.'|'.$l->direction) as $group) {
            $first = $group->first();
            $from = CarbonImmutable::parse($group->min('date'))->subDays($window)->startOfDay();
            $to = CarbonImmutable::parse($group->max('date'))->addDays($window)->endOfDay();
            $pool = $this->pool((int) $first->tenant_id, (int) $first->bank_id, $first->direction, $from, $to);

            foreach ($group as $line) {
                $rejected = $line->rejectedKeys();
                foreach ($pool as $candidate) {
                    if (in_array($candidate->key(), $rejected, true) || ! $this->fits($line, $candidate, $window)) {
                        continue;
                    }
                    $scored[] = [$this->score($line, $candidate), $line->id, $candidate];
                }
            }
        }

        // Best pairs first; a line or a record is used once.
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
        $result = [];
        $usedRecords = [];
        foreach ($scored as [$score, $lineId, $candidate]) {
            if (isset($result[$lineId]) || isset($usedRecords[$candidate->key()])) {
                continue;
            }
            $result[$lineId] = new Suggestion($candidate, $score);
            $usedRecords[$candidate->key()] = true;
        }

        return $result;
    }

    /** Is this record still a valid match for the line (checked again when accepting)? */
    public function candidateFor(BankFeedLine $line, string $recordType, int $recordId): ?Candidate
    {
        $window = (int) config('mybooks.bank_feeds.match_window_days', 5);
        $date = CarbonImmutable::parse($line->date);
        $pool = $this->pool((int) $line->tenant_id, (int) $line->bank_id, $line->direction, $date->subDays($window)->startOfDay(), $date->addDays($window)->endOfDay());

        return $pool->first(fn (Candidate $c) => $recordType === $c->record::class && (int) $c->record->getKey() === $recordId && $this->fits($line, $c, $window));
    }

    /**
     * Records on this bank account, from $from on, that no bank line is
     * linked to yet: what the books have that the bank has not shown.
     *
     * @return Collection<int, Candidate>
     */
    public function booksWithoutLine(Bank $bank, CarbonImmutable $from, int $limit = 200): Collection
    {
        $to = CarbonImmutable::now()->addDay()->endOfDay();

        return collect(['credit', 'debit'])
            ->flatMap(fn (string $direction) => $this->pool((int) $bank->tenant_id, (int) $bank->id, $direction, $from, $to))
            ->sortByDesc(fn (Candidate $c) => $c->date->timestamp)
            ->take($limit)
            ->values();
    }

    private function fits(BankFeedLine $line, Candidate $candidate, int $window): bool
    {
        return abs($candidate->amount - (float) $line->amount) < 0.005
            && abs(CarbonImmutable::parse($line->date)->startOfDay()->diffInDays($candidate->date->startOfDay(), false)) <= $window;
    }

    private function score(BankFeedLine $line, Candidate $candidate): int
    {
        $days = (int) abs(CarbonImmutable::parse($line->date)->startOfDay()->diffInDays($candidate->date->startOfDay(), false));
        $dateScore = match (true) {
            $days === 0 => 25,
            $days === 1 => 20,
            $days === 2 => 15,
            $days === 3 => 10,
            default => 5,
        };

        return min(100, 50 + $dateScore + $this->textScore((string) $line->narration, $candidate));
    }

    /** Up to 25: the record's number or reference in the narration, else the name's words. */
    private function textScore(string $narration, Candidate $candidate): int
    {
        $flat = $this->flatten($narration);
        if ($flat === '') {
            return 0;
        }
        foreach ($candidate->references as $reference) {
            $ref = $this->flatten($reference);
            if (strlen($ref) >= 4 && str_contains($flat, $ref)) {
                return 25;
            }
        }

        $words = collect(preg_split('/[^a-z0-9]+/', Str::lower((string) $candidate->party)) ?: [])
            ->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, self::NOISE, true))->unique()->values();
        if ($words->isEmpty()) {
            return 0;
        }
        $hits = $words->filter(fn ($w) => str_contains($flat, $w))->count();

        return (int) round(20 * $hits / $words->count());
    }

    private function flatten(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower($text)) ?? '';
    }

    // ---- candidate pools -----------------------------------------------

    /** @return Collection<int, Candidate> */
    private function pool(int $tenantId, int $bankId, string $direction, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $items = $direction === 'credit'
            ? [...$this->paymentsReceived($tenantId, $bankId, $from, $to), ...$this->vendorCreditRefunds($tenantId, $bankId, $from, $to), ...$this->transfers($tenantId, $bankId, 'credit', $from, $to)]
            : [...$this->paymentsMade($tenantId, $bankId, $from, $to), ...$this->expenses($tenantId, $bankId, $from, $to), ...$this->remittances($tenantId, $bankId, $from, $to), ...$this->creditNoteRefunds($tenantId, $bankId, $from, $to), ...$this->transfers($tenantId, $bankId, 'debit', $from, $to)];

        return collect($items);
    }

    /**
     * Leaves out records some line of this direction is already linked to.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function unlinked(Builder $query, string $class, string $direction, int $tenantId): Builder
    {
        return $query->whereNotIn($query->getModel()->getQualifiedKeyName(), BankFeedLine::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('matched_type', $class)->where('direction', $direction)->whereNotNull('matched_id')->select('matched_id'));
    }

    /** @return list<Candidate> */
    private function paymentsReceived(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = PaymentReceived::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)
            ->whereBetween('payment_date', [$from, $to])->with(['customer:id,name', 'invoice:id,invoice_number']);

        return $this->unlinked($q, PaymentReceived::class, 'credit', $tenantId)->get()->map(fn (PaymentReceived $p) => new Candidate(
            $p, 'Payment received', (string) $p->payment_number, $p->payment_date, (float) $p->amount, $p->customer?->name,
            array_filter([$p->payment_number, $p->reference, $p->invoice?->invoice_number]), route('payments-received.show', $p->id),
        ))->all();
    }

    /** @return list<Candidate> */
    private function vendorCreditRefunds(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = VendorCreditRefund::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)
            ->whereBetween('refund_date', [$from, $to])->with('vendorCredit.vendor:id,name');

        return $this->unlinked($q, VendorCreditRefund::class, 'credit', $tenantId)->get()->map(fn (VendorCreditRefund $r) => new Candidate(
            $r, 'Supplier refund', (string) ($r->vendorCredit?->vendor_credit_number ?: '#'.$r->id), $r->refund_date, (float) $r->amount, $r->vendorCredit?->vendor?->name,
            array_filter([$r->reference, $r->vendorCredit?->vendor_credit_number]), $r->vendorCredit ? route('vendor-credits.show', $r->vendorCredit->id) : null,
        ))->all();
    }

    /** @return list<Candidate> */
    private function paymentsMade(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = PaymentMade::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)
            ->whereBetween('payment_date', [$from, $to])->with(['vendor:id,name', 'bill:id,bill_number']);

        return $this->unlinked($q, PaymentMade::class, 'debit', $tenantId)->get()->map(fn (PaymentMade $p) => new Candidate(
            $p, 'Payment made', (string) $p->payment_number, $p->payment_date, (float) $p->amount, $p->vendor?->name,
            array_filter([$p->payment_number, $p->reference, $p->bill?->bill_number]), route('payments-made.show', $p->id),
        ))->all();
    }

    /** Only paid expenses are in the books, so only they can be the bank's debit. @return list<Candidate> */
    private function expenses(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = Expense::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)
            ->where('status', Expense::STATUS_PAID)->whereBetween('expense_date', [$from, $to])->with('vendor:id,name');

        return $this->unlinked($q, Expense::class, 'debit', $tenantId)->get()->map(fn (Expense $e) => new Candidate(
            $e, 'Expense', (string) $e->expense_number, $e->expense_date, (float) $e->total, $e->vendor?->name ?: $e->name,
            array_filter([$e->expense_number, $e->reference]), route('expenses.show', $e->id),
        ))->all();
    }

    /** @return list<Candidate> */
    private function remittances(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = StatutoryRemittance::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)->whereBetween('paid_on', [$from, $to]);

        return $this->unlinked($q, StatutoryRemittance::class, 'debit', $tenantId)->get()->map(fn (StatutoryRemittance $r) => new Candidate(
            $r, 'Tax remittance', '#'.$r->id, $r->paid_on, (float) $r->amount, $r->paid_to,
            array_filter([$r->reference]), null,
        ))->all();
    }

    /** @return list<Candidate> */
    private function creditNoteRefunds(int $tenantId, int $bankId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = CreditNoteRefund::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('bank_id', $bankId)
            ->whereBetween('refund_date', [$from, $to])->with('creditNote.customer:id,name');

        return $this->unlinked($q, CreditNoteRefund::class, 'debit', $tenantId)->get()->map(fn (CreditNoteRefund $r) => new Candidate(
            $r, 'Customer refund', (string) ($r->creditNote?->credit_note_number ?: '#'.$r->id), $r->refund_date, (float) $r->amount, $r->creditNote?->customer?->name,
            array_filter([$r->reference, $r->creditNote?->credit_note_number]), $r->creditNote ? route('credit-notes.show', $r->creditNote->id) : null,
        ))->all();
    }

    /**
     * A transfer posted from a line on the other account: this account's
     * ledger is debited (money in) or credited (money out) for the amount.
     *
     * @return list<Candidate>
     */
    private function transfers(int $tenantId, int $bankId, string $direction, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $accountId = $this->ledgerAccountId($tenantId, $bankId);
        if (! $accountId) {
            return [];
        }
        $column = $direction === 'credit' ? 'debit' : 'credit';

        $journals = Journal::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('journal_type', 'bank_transfer')
            ->where('reference_type', BankFeedLine::class)->whereBetween('journal_date', [$from, $to])
            ->whereHas('entries', fn ($e) => $e->where('account_id', $accountId)->where($column, '>', 0))
            ->with(['entries' => fn ($e) => $e->where('account_id', $accountId)]);
        $journals = $this->unlinked($journals, Journal::class, $direction, $tenantId)->get();

        // Not the account's own side of it.
        $origins = BankFeedLine::withoutGlobalScopes()->whereIn('id', $journals->pluck('reference_id'))->pluck('bank_id', 'id');

        return $journals->filter(fn (Journal $j) => (int) ($origins[$j->reference_id] ?? 0) !== $bankId)->map(fn (Journal $j) => new Candidate(
            $j, 'Transfer', (string) $j->journal_number, $j->journal_date, (float) $j->entries->sum($column), null,
            [(string) $j->reference], route('journals.show', $j->id),
        ))->values()->all();
    }

    /** The ledger account money in this bank account is posted to. */
    public function ledgerAccountId(int $tenantId, int $bankId): ?int
    {
        $bank = Bank::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($bankId);
        if (! $bank) {
            return null;
        }
        if ($bank->chart_of_account_id) {
            return (int) $bank->chart_of_account_id;
        }

        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('account_code', AccountCodeService::resolvePaymentMethod($tenantId, 'bank_transfer'))->value('id');
    }
}
