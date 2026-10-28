<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\BankLines;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 10a (MB-08, part 2): the lines to tick
 * on the reconcile screen came only from bank feed matching, so a business
 * without a feed could never reconcile. Without a feed, every posted entry
 * on the bank's ledger account is now a line to tick.
 */
class Session10LedgerLinesTest extends TestCase
{
    private const PERMISSIONS = [
        'view banks', 'reconcile banks', 'view invoices', 'create invoices', 'view payments-received', 'create payments-received',
        'view journals', 'create journals', 'post journals',
    ];

    private ChartOfAccount $ledger;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);

        $this->ledger = ChartOfAccount::factory()->asset()->create(['tenant_id' => $this->tenant->id, 'account_code' => '1120', 'name' => 'GTBank current', 'sub_type' => 'bank']);
        $this->bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'GTBank', 'chart_of_account_id' => $this->ledger->id, 'opening_balance' => 1000, 'current_balance' => 1000]);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function journal(string $date, float $in, float $out, string $other = '6990'): Journal
    {
        $this->post(route('journals.store'), ['journal_date' => $date, 'description' => 'Manual', 'entries' => [
            ['account_id' => $this->ledger->id, 'debit' => $in, 'credit' => $out],
            ['account_id' => $this->account($other)->id, 'debit' => $out, 'credit' => $in],
        ]])->assertSessionHasNoErrors();
        $journal = Journal::where('is_posted', false)->latest('id')->firstOrFail();
        $this->post(route('journals.post', $journal))->assertSessionHasNoErrors();

        return $journal->fresh();
    }

    private function receipt(float $amount): void
    {
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31', 'status' => 'sent',
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
        $this->post(route('payments-received.store'), [
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-05',
            'amount' => $amount, 'payment_method' => 'bank_transfer', 'bank_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();
    }

    private function lines()
    {
        return BankTransaction::where('bank_id', $this->bank->id)->orderBy('date')->orderBy('id')->get();
    }

    public function test_receipts_payments_and_journals_appear_once_each(): void
    {
        $this->receipt(4000);
        $this->journal('2026-10-06', 0, 150); // bank charges

        $this->get(route('banks.reconcile', $this->bank))->assertOk()
            ->assertSee('4,000.00')->assertSee('150.00')
            ->assertDontSee('All caught up');
        $this->get(route('banks.reconcile', $this->bank))->assertOk();

        $lines = $this->lines();
        $this->assertCount(2, $lines, 'viewing twice adds nothing');
        $this->assertSame([BankTransaction::TYPE_DEPOSIT, BankTransaction::TYPE_WITHDRAWAL], $lines->pluck('type')->all());
        $this->assertSame(['4000.00', '150.00'], $lines->pluck('amount')->all());
        $this->assertSame(['2026-10-05', '2026-10-06'], $lines->map(fn ($l) => $l->date->toDateString())->all());
    }

    public function test_ticking_to_the_statement_balance_leaves_no_difference(): void
    {
        $this->receipt(4000);
        $this->journal('2026-10-06', 0, 150);
        $this->get(route('banks.reconcile', $this->bank));

        // Opening 1,000 + 4,000 - 150. A wrong statement balance is refused.
        $ids = $this->lines()->pluck('id')->all();
        $this->post(route('banks.reconcile.process', $this->bank), ['transaction_ids' => $ids, 'statement_balance' => 4900, 'statement_date' => '2026-10-09'])
            ->assertSessionHas('error');
        $this->post(route('banks.reconcile.process', $this->bank), ['transaction_ids' => $ids, 'statement_balance' => 4850, 'statement_date' => '2026-10-09'])
            ->assertSessionHas('success');

        $this->get(route('banks.reconcile', $this->bank))->assertOk()->assertSee('All caught up');
        $this->assertSame(0, $this->lines()->where('is_reconciled', false)->count());
    }

    public function test_draft_journals_and_other_accounts_are_left_out(): void
    {
        $this->post(route('journals.store'), ['journal_date' => '2026-10-06', 'description' => 'Draft', 'entries' => [
            ['account_id' => $this->ledger->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $this->account('4000')->id, 'debit' => 0, 'credit' => 500],
        ]])->assertSessionHasNoErrors();
        $this->post(route('journals.store'), ['journal_date' => '2026-10-06', 'description' => 'Cash sale', 'entries' => [
            ['account_id' => $this->account('1000')->id, 'debit' => 700, 'credit' => 0],
            ['account_id' => $this->account('4000')->id, 'debit' => 0, 'credit' => 700],
        ]])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::where('description', 'Cash sale')->sole()))->assertSessionHasNoErrors();

        $this->assertSame(0, app(BankLines::class)->sync($this->bank));
    }

    public function test_an_unticked_line_follows_the_books_and_a_ticked_one_stays(): void
    {
        $kept = $this->journal('2026-10-06', 0, 150);
        $dropped = $this->journal('2026-10-07', 0, 80);
        app(BankLines::class)->sync($this->bank);
        $tick = $this->lines()->firstWhere('journal_id', $kept->id);
        $this->post(route('banks.reconcile.process', $this->bank), ['transaction_ids' => [$tick->id], 'statement_balance' => 850, 'statement_date' => '2026-10-06'])
            ->assertSessionHas('success');

        // Both journals' lines rewritten (as an edit did before audit MB-15; data
        // from then is still about), one journal later deleted.
        JournalEntry::rebuilding(function () use ($kept, $dropped) {
            foreach ([$kept, $dropped] as $journal) {
                foreach ($journal->entries as $entry) {
                    $copy = $entry->replicate();
                    $entry->delete();
                    $copy->save();
                }
            }
        });
        $dropped->delete();
        app(BankLines::class)->sync($this->bank);

        $lines = $this->lines();
        $this->assertCount(1, $lines, 'the deleted journal\'s unticked line is gone');
        $this->assertTrue($lines->first()->is_reconciled);
        $this->assertSame(JournalEntry::where('journal_id', $kept->id)->where('account_id', $this->ledger->id)->value('id'), $lines->first()->journal_entry_id, 'the ticked line moved to the new entry');
    }

    public function test_a_line_in_a_locked_month_is_still_listed(): void
    {
        $this->journal('2026-09-20', 300, 0, '4000');
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Q3 filed']);

        $this->assertSame(1, app(BankLines::class)->sync($this->bank));
    }

    public function test_a_bank_on_a_live_feed_is_left_alone(): void
    {
        BankFeedConnection::create(['tenant_id' => $this->tenant->id, 'bank_id' => $this->bank->id, 'provider' => 'mono', 'status' => 'linked']);
        $this->journal('2026-10-06', 0, 150);

        $this->assertSame(0, app(BankLines::class)->sync($this->bank));
        $this->get(route('banks.reconcile', $this->bank))->assertOk()->assertSee('Lines come from the bank feed');
    }

    public function test_a_bank_without_a_ledger_account_says_so(): void
    {
        $box = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'chart_of_account_id' => null, 'opening_balance' => 0, 'current_balance' => 200]);

        $this->get(route('banks.reconcile', $box))->assertOk()
            ->assertDontSee('All caught up')
            ->assertSee('isn&#039;t linked to a ledger account', false);
    }
}
