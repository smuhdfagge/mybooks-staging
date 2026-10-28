<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\Payments\RecordPaymentReceived;
use App\Exceptions\BusinessRuleException;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use App\Services\Statements\Subledger;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 17 (MB-15): editing a document rewrote
 * its posted journal in place. The balances were taken back, the lines
 * deleted with a query that went round the line guard, and new ones
 * written, so nothing showed what the journal had said. Now an edit that
 * changes the figures reverses the journal and posts a new one; an edit
 * that doesn't changes nothing, and a posted journal's lines can't be
 * changed or deleted at all.
 */
class Session17ReverseAndRepostTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
        $this->createAuthenticatedUser(['view invoices', 'create invoices', 'edit invoices', 'view bills', 'create bills', 'edit bills']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function invoice(float $price = 10000): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'status' => 'sent',
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $price, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    /** @return Collection<int, Journal> */
    private function journals(string $type, int $id)
    {
        return Journal::where('reference_type', $type)->where('reference_id', $id)->orderBy('id')->get();
    }

    private function ledger(string $code): float
    {
        return (float) app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2030-12-31')->firstWhere('account_code', $code)->balance;
    }

    /** @return list<array{int, string, string}> */
    private function lines(Journal $journal): array
    {
        return $journal->entries()->orderBy('id')->get()
            ->map(fn (JournalEntry $e) => [$e->id, (string) $e->debit, (string) $e->credit])->all();
    }

    public function test_editing_an_invoice_reverses_its_journal_and_posts_a_new_one(): void
    {
        $invoice = $this->invoice();
        $original = Journal::currentFor($invoice);
        $before = $this->lines($original);

        app(SaveInvoice::class)->update($invoice, ['items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);

        [$first, $reversal, $new] = $this->journals(Invoice::class, $invoice->id)->sortBy('journal_number')->values()->all();
        $this->assertSame($original->id, $first->id);
        $this->assertSame('reversed', $first->status);
        $this->assertSame($before, $this->lines($first), 'the original journal is untouched');

        $this->assertSame("REV-{$original->journal_number}", $reversal->reference);
        $this->assertSame('2026-10-05', $reversal->journal_date->toDateString(), 'reversed on its own date, so October still shows what the invoice now says');
        $this->assertEqualsWithDelta(10750, (float) $reversal->total_credit, 0.001);

        $this->assertSame('posted', $new->status);
        $this->assertEqualsWithDelta(21500, (float) $new->total_debit, 0.001);
        $this->assertSame($new->id, Journal::currentFor($invoice)->id);
        $this->assertSame($new->id, $invoice->fresh()->journal->id, 'the invoice page shows the journal it stands on');

        $this->assertEqualsWithDelta(21500, $this->ledger('1200'), 0.001, 'receivables');
        $this->assertEqualsWithDelta(21500, (float) ChartOfAccount::where('account_code', '1200')->sole()->current_balance, 0.001);
        $this->assertEqualsWithDelta(1500, abs($this->ledger('2400')), 0.001, 'output VAT');
    }

    public function test_an_edit_that_changes_no_figures_posts_nothing(): void
    {
        $invoice = $this->invoice();
        $journal = Journal::currentFor($invoice);
        $lines = $this->lines($journal);

        app(SaveInvoice::class)->update($invoice, ['notes' => 'Thanks for your business', 'due_date' => '2026-11-30',
            'items' => [['description' => 'Consulting (October)', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);

        $this->assertSame([$journal->id], $this->journals(Invoice::class, $invoice->id)->pluck('id')->all());
        $this->assertSame($lines, $this->lines($journal->fresh()));

        // No journal number used up either: the next journal follows on.
        $next = $this->invoice();
        $this->assertSame((int) substr($journal->journal_number, -6) + 1, (int) substr(Journal::currentFor($next)->journal_number, -6));
    }

    public function test_a_payment_never_reverses_the_invoice_journal(): void
    {
        $invoice = $this->invoice();
        $journal = Journal::currentFor($invoice);

        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 4000, 'payment_method' => 'cash',
        ], $this->user->id);

        $this->assertSame([$journal->id], $this->journals(Invoice::class, $invoice->id)->pluck('id')->all());
        $this->assertSame('posted', $journal->fresh()->status);
    }

    public function test_moving_a_payment_to_another_day_reverses_and_reposts_it(): void
    {
        $invoice = $this->invoice();
        $payment = app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-10', 'amount' => 4000, 'payment_method' => 'cash',
        ], $this->user->id);
        $cash = $this->ledger('1000');

        $payment->update(['notes' => 'Paid at the counter']);
        $this->assertCount(1, $this->journals($payment::class, $payment->id), 'no figures changed');

        $payment->update(['payment_date' => '2026-10-12']);
        $journals = $this->journals($payment::class, $payment->id);
        $this->assertSame(['reversed', 'posted', 'posted'], $journals->pluck('status')->all());
        $this->assertSame(['2026-10-10', '2026-10-10', '2026-10-12'], $journals->map(fn ($j) => $j->journal_date->toDateString())->all(),
            'the reversal on the old day, the new journal on the new one');
        $this->assertEqualsWithDelta($cash, $this->ledger('1000'), 0.001);
    }

    public function test_editing_a_bill_reverses_and_reposts_too(): void
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-10-05', 'due_date' => '2026-11-05',
            'items' => [['description' => 'Diesel', 'quantity' => 1, 'unit_price' => 8000, 'tax_rate' => 0]],
        ], $this->user->id);

        app(SaveBill::class)->update($bill, ['items' => [['description' => 'Diesel', 'quantity' => 1, 'unit_price' => 9000, 'tax_rate' => 0]]]);

        $journals = $this->journals(Bill::class, $bill->id);
        $this->assertCount(3, $journals);
        $this->assertSame(['reversed', 'posted', 'posted'], $journals->pluck('status')->all());
        $this->assertEqualsWithDelta(9000, abs($this->ledger('2000')), 0.001, 'payables');
        $this->assertEqualsWithDelta(9000, (float) Journal::currentFor($bill)->total_credit, 0.001);
    }

    public function test_an_edited_invoice_stays_on_the_customer_statement_and_a_cancelled_one_comes_off(): void
    {
        $invoice = $this->invoice();
        app(SaveInvoice::class)->update($invoice, ['items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);

        $statement = app(Subledger::class)->movements($this->tenant->id, Subledger::CUSTOMERS, $this->customer->id);
        $this->assertSame(['invoice'], $statement->pluck('kind')->all(), 'its reversal is not a cancellation');
        $this->assertEqualsWithDelta(21500, app(Subledger::class)->balances($this->tenant->id, Subledger::CUSTOMERS)[$this->customer->id], 0.001);

        $this->travelTo('2026-10-25 09:00:00');
        $invoice->fresh()->update(['status' => 'cancelled']);

        $statement = app(Subledger::class)->movements($this->tenant->id, Subledger::CUSTOMERS, $this->customer->id);
        $this->assertSame(['invoice', 'reversal'], $statement->pluck('kind')->all());
        $this->assertSame('2026-10-25', $statement->last()->date, 'taken off the day it was cancelled, not the day it was edited');
        $this->assertEqualsWithDelta(0, $this->ledger('1200'), 0.001);
    }

    public function test_a_posted_journals_lines_cant_be_changed_or_deleted(): void
    {
        $journal = Journal::currentFor($this->invoice());
        $entry = $journal->entries()->firstOrFail();
        $debit = (string) $entry->debit;

        foreach ([
            fn () => $journal->entries()->delete(),
            fn () => JournalEntry::where('journal_id', $journal->id)->update(['debit' => 1]),
            fn () => JournalEntry::query()->whereKey($entry->id)->forceDelete(),
            fn () => $entry->delete(),
            fn () => $entry->forceFill(['debit' => 1])->save(),
        ] as $i => $attempt) {
            try {
                $attempt();
                $this->fail("attempt {$i} went through");
            } catch (BusinessRuleException $e) {
                $this->assertStringContainsString("A posted journal's lines can't be", $e->getMessage());
            }
        }
        $this->assertCount(count($this->lines($journal)), $journal->entries()->get());
        $this->assertSame($debit, (string) $entry->fresh()->debit);

        // A draft's lines can.
        $draft = Journal::create(['tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id), 'journal_date' => '2026-10-10',
            'description' => 'Draft', 'status' => 'draft', 'is_posted' => false, 'created_by' => $this->user->id]);
        JournalEntry::create(['journal_id' => $draft->id, 'account_id' => $entry->account_id, 'debit' => 5, 'credit' => 0]);
        $this->assertSame(1, $draft->entries()->delete());
    }
}
