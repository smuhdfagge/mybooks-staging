<?php

namespace App\Services\BankFeeds;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Payments\RecordPaymentReceived;
use App\Enums\BankFeedLineStatus;
use App\Models\Bank;
use App\Models\BankFeedLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentReceived;
use App\Models\User;
use App\Services\AccountCodeService;
use App\Services\BankService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a MyBooks record from a bank line that matches nothing (session
 * 17), only when a person asks. Everything goes through the normal route
 * for that kind of record, so journals, WHT/VAT rules, approvals and lock
 * dates apply exactly as when it is entered by hand. A lock date or closed
 * period refuses with the usual message and the line stays as it was.
 *
 *  - money in, from a customer: RecordPaymentReceived (on an invoice, or a
 *    customer deposit);
 *  - money out: CreateExpense, a draft that is approved and paid as any
 *    expense is (so it counts as cleared once it is paid);
 *  - other income, a bank charge, a transfer: one balanced journal through
 *    JournalService::postLines, with the bank balance moved by BankService.
 */
class LinePoster
{
    public function __construct(
        private LineActions $actions,
        private RecordPaymentReceived $paymentReceived,
        private CreateExpense $createExpense,
        private JournalService $journals,
        private BankService $banks,
        private Matcher $matcher,
    ) {}

    /** @throws BankFeedException */
    public function paymentReceived(BankFeedLine $line, User $user, int $customerId, ?int $invoiceId, bool $asDeposit = false): PaymentReceived
    {
        $this->assertNew($line, $user, 'credit');
        $customer = Customer::where('tenant_id', $user->tenant_id)->find($customerId) ?? throw new BankFeedException('Choose a customer.');
        $invoice = null;
        if (! $asDeposit && $invoiceId) {
            $invoice = Invoice::where('tenant_id', $user->tenant_id)->where('customer_id', $customer->id)->find($invoiceId)
                ?? throw new BankFeedException('That invoice does not belong to this customer.');
        }

        return DB::transaction(function () use ($line, $user, $customer, $invoice, $asDeposit) {
            $payment = $this->paymentReceived->handle((int) $user->tenant_id, [
                'customer_id' => $customer->id,
                'invoice_id' => $invoice?->id,
                'payment_date' => $line->date->toDateString(),
                'amount' => (float) $line->amount,
                'payment_method' => 'bank_transfer',
                'bank_id' => $line->bank_id,
                'reference' => $this->reference($line),
                'notes' => 'From the bank feed',
                'is_deposit' => $asDeposit || ! $invoice,
            ], $user->id);
            $this->actions->link($line, $payment, BankFeedLineStatus::Created, $user);

            return $payment;
        });
    }

    /**
     * An expense for money that left the bank, saved as a draft.
     *
     * @param  array{name: string, expense_account_id: int, vendor_id?: ?int, notes?: ?string}  $data
     */
    public function expense(BankFeedLine $line, User $user, array $data): Expense
    {
        $this->assertNew($line, $user, 'debit');
        $account = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'expense')->find($data['expense_account_id'])
            ?? throw new BankFeedException('Choose what the money was spent on.');

        return DB::transaction(function () use ($line, $user, $data, $account) {
            $expense = $this->createExpense->handle((int) $user->tenant_id, [
                'name' => Str::limit(trim($data['name']) ?: 'Bank payment', 255, ''),
                'expense_date' => $line->date->toDateString(),
                'expense_account_id' => $account->id,
                'amount' => (float) $line->amount,
                'tax_amount' => 0,
                'vendor_id' => $data['vendor_id'] ?? null,
                'bank_id' => $line->bank_id,
                'payment_method' => 'bank_transfer',
                'reference' => $this->reference($line),
                'notes' => $data['notes'] ?? 'From the bank feed',
            ], $user->id);
            $this->actions->link($line, $expense, BankFeedLineStatus::Created, $user);

            return $expense;
        });
    }

    /** Money in that isn't a customer payment: Dr bank, Cr the income account. */
    public function otherIncome(BankFeedLine $line, User $user, int $incomeAccountId, ?string $description = null): Journal
    {
        $this->assertNew($line, $user, 'credit');
        $account = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'income')->find($incomeAccountId)
            ?? throw new BankFeedException('Choose an income account.');
        $text = Str::limit(trim((string) $description) ?: 'Other income', 150, '');

        return $this->postBankJournal($line, $user, 'Other income - '.$text, $account->account_code, 'in');
    }

    /** Fees the bank took: Dr the charges account, Cr bank. */
    public function bankCharge(BankFeedLine $line, User $user, int $expenseAccountId): Journal
    {
        $this->assertNew($line, $user, 'debit');
        $account = ChartOfAccount::where('tenant_id', $user->tenant_id)->where('type', 'expense')->find($expenseAccountId)
            ?? throw new BankFeedException('Choose the account for bank charges.');

        return $this->postBankJournal($line, $user, 'Bank charge - '.Str::limit((string) $line->narration, 120, ''), $account->account_code, 'out');
    }

    /** Between two of the business's own accounts. The other account's own line is then matched to this journal. */
    public function transfer(BankFeedLine $line, User $user, int $otherBankId): Journal
    {
        $this->assertNew($line, $user);
        $other = Bank::where('tenant_id', $user->tenant_id)->find($otherBankId) ?? throw new BankFeedException('Choose the other bank account.');
        if ((int) $other->id === (int) $line->bank_id) {
            throw new BankFeedException('Choose a different bank account.');
        }
        $otherAccount = $this->matcher->ledgerAccountId((int) $user->tenant_id, (int) $other->id);
        $code = $otherAccount ? ChartOfAccount::whereKey($otherAccount)->value('account_code') : null;
        if (! $code) {
            throw new BankFeedException('The other bank account has no ledger account yet.');
        }

        $direction = $line->isCredit() ? 'in' : 'out';

        return DB::transaction(function () use ($line, $user, $other, $code, $direction) {
            $journal = $this->postBankJournal($line, $user, ($direction === 'in' ? 'Transfer from ' : 'Transfer to ').$other->name, $code, $direction, 'bank_transfer', false);
            $this->banks->{$direction === 'in' ? 'debit' : 'credit'}($other->id, (float) $line->amount, "Transfer #{$journal->journal_number}");

            $this->actions->link($line, $journal, BankFeedLineStatus::Created, $user);

            return $journal;
        });
    }

    /**
     * One balanced journal between this bank's ledger account and $otherCode,
     * the bank balance moved, and the line linked.
     */
    private function postBankJournal(BankFeedLine $line, User $user, string $description, string $otherCode, string $direction, string $type = 'bank_feed', bool $link = true): Journal
    {
        $bank = Bank::where('tenant_id', $user->tenant_id)->findOrFail($line->bank_id);
        $bankCode = $this->bankLedgerCode($bank);
        $amount = (float) $line->amount;
        $text = Str::limit($description, 190, '');

        $post = function () use ($line, $user, $text, $bankCode, $otherCode, $amount, $direction, $type, $link, $bank) {
            $lines = $direction === 'in'
                ? [[$bankCode, $amount, 0.0, $text], [$otherCode, 0.0, $amount, $text]]
                : [[$otherCode, $amount, 0.0, $text], [$bankCode, 0.0, $amount, $text]];
            $journal = $this->journals->postLines($line, $this->reference($line), $line->date->toDateString(), $text, $lines, $user->id, $type);

            $direction === 'in'
                ? $this->banks->credit($bank->id, $amount, "Bank feed line #{$line->id}")
                : $this->banks->debit($bank->id, $amount, "Bank feed line #{$line->id}");

            if ($link) {
                $this->actions->link($line, $journal, BankFeedLineStatus::Created, $user);
            }

            return $journal;
        };

        return $link ? DB::transaction($post) : $post();
    }

    private function bankLedgerCode(Bank $bank): string
    {
        if ($bank->chart_of_account_id) {
            $code = ChartOfAccount::whereKey($bank->chart_of_account_id)->value('account_code');
            if ($code) {
                return $code;
            }
        }

        return AccountCodeService::resolvePaymentMethod((int) $bank->tenant_id, 'bank_transfer');
    }

    private function reference(BankFeedLine $line): string
    {
        return Str::limit(trim((string) $line->narration) ?: 'Bank feed line '.$line->id, 100, '');
    }

    private function assertNew(BankFeedLine $line, User $user, ?string $direction = null): void
    {
        if ((int) $line->tenant_id !== (int) $user->tenant_id) {
            throw new BankFeedException('That bank line was not found.');
        }
        if (! $line->isNew()) {
            throw new BankFeedException('This bank line has already been dealt with.');
        }
        if ($direction && $line->direction !== $direction) {
            throw new BankFeedException($direction === 'credit' ? 'This is money going out, not coming in.' : 'This is money coming in, not going out.');
        }
    }
}
