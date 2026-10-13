<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\CreditNotes\ApplyCreditNote;
use App\Actions\CreditNotes\OpenCreditNote;
use App\Actions\CreditNotes\RefundCreditNote;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\CreditNotes\VoidCreditNote;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\Payments\RecordPaymentReceived;
use App\Livewire\CreditNotes\CreditNotesTable;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteRefund;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 7: customer credit notes, with returned goods going back into
 * stock (finding A15).
 */
class CustomerCreditNotesTest extends TestCase
{
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser([
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'view customers',
            'create payments-received', 'create bills', 'view bills', 'view reports',
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aminu Stores']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    /** A stock item with 10 bought at 1,000 each. */
    private function stockedItem(): Item
    {
        $item = Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Rice bag', 'cost_price' => 0, 'selling_price' => 1500, 'valuation_method' => 'weighted_average',
        ]);
        $this->buy($item, 10, 1000, '2026-10-01');

        return $item;
    }

    private function buy(Item $item, float $qty, float $cost, string $date): void
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => $cost, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function invoice(array $lines, string $date = '2026-10-05'): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => $date, 'due_date' => '2026-11-05',
            'status' => 'unpaid', 'items' => $lines,
        ], $this->user->id);
    }

    /** Invoice 4 rice bags at 1,500 + 7.5% VAT (6,450), paid in full and released (the goods leave). */
    private function soldAndDelivered(Item $item): Invoice
    {
        $invoice = $this->invoice([['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 4, 'unit_price' => 1500, 'tax_rate' => 7.5]]);
        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-06',
            'amount' => 6450, 'payment_method' => 'cash',
        ], $this->user->id);
        $this->post(route('invoices.release', $invoice))->assertSessionHas('success');

        return $invoice->fresh();
    }

    /** @param array<string, mixed> $data */
    private function note(array $data, string $status = 'open'): CreditNote
    {
        return app(SaveCreditNote::class)->create($this->tenant->id, $data + [
            'customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-10', 'reason' => 'product_return', 'status' => $status,
        ], $this->user->id);
    }

    private function serviceNote(float $price = 10000, string $status = 'open'): CreditNote
    {
        return $this->note(['items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $price, 'tax_rate' => 7.5]]], $status);
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    private function stock(Item $item): float
    {
        return (float) Inventory::where('item_id', $item->id)->value('quantity');
    }

    private function assertBooksBalance(): void
    {
        $tb = app(FinancialStatements::class)->trialBalance($this->tenant->id, '2026-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    /** @return array<string, array{0: float, 1: float}> account code => [debit, credit] */
    private function journalLines(string $type, int $id): array
    {
        $journal = Journal::where('reference_type', $type)->where('reference_id', $id)
            ->whereNot('reference', 'like', 'REV-%')->with('entries.account')->firstOrFail();

        return $journal->entries->groupBy(fn ($e) => $e->account->account_code)
            ->map(fn ($g) => [round((float) $g->sum('debit'), 2), round((float) $g->sum('credit'), 2)])->all();
    }

    // ---- posting -------------------------------------------------------

    public function test_opening_a_credit_note_takes_the_sale_off_revenue_vat_and_receivables(): void
    {
        $this->invoice([['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 7.5]]);
        $this->assertSame(107500.0, $this->balance('1200'));

        $draft = $this->serviceNote(10000, 'draft');
        $this->assertSame('draft', $draft->status);
        $this->assertStringStartsWith('CN-', $draft->credit_note_number);
        $this->assertEquals(10750, (float) $draft->total);
        $this->assertSame(107500.0, $this->balance('1200'), 'a draft posts nothing');
        $this->assertSame(0, Journal::where('reference_type', CreditNote::class)->count());

        app(OpenCreditNote::class)->handle($draft);
        $note = $draft->fresh();
        $this->assertSame('open', $note->status);
        $this->assertEquals(10750, (float) $note->balance);

        $this->assertSame(['1200' => [0.0, 10750.0], '2400' => [750.0, 0.0], '4000' => [10000.0, 0.0]],
            collect($this->journalLines(CreditNote::class, $note->id))->sortKeys()->all());
        $this->assertSame(96750.0, $this->balance('1200'));
        $this->assertSame(90000.0, $this->balance('4000'));
        $this->assertSame(6750.0, $this->balance('2400'));
        $this->assertBooksBalance();

        // An open credit note can't be opened again or changed.
        $this->expectException(ValidationException::class);
        app(SaveCreditNote::class)->update($note, ['customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-10', 'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0]]]);
    }

    public function test_a15_returned_goods_go_back_into_stock_at_the_cost_the_invoice_took_them_out_at(): void
    {
        config(['mybooks.features.credit_notes' => true]);
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);
        $this->assertSame(6.0, $this->stock($item));
        $this->assertSame(6000.0, $this->balance('1300'));
        $this->assertSame(4000.0, $this->balance('5000'));
        // Bought again later at a higher cost: the average moves, but the
        // returned bags cost what the invoice took them out at.
        $this->buy($item, 2, 1600, '2026-10-08');

        // Through the screens, as a user would: goods returned, then posted.
        $this->post(route('credit-notes.store'), [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'credit_note_date' => '2026-10-10',
            'reason' => 'product_return', 'restock' => '1',
            'items' => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 7.5]],
        ])->assertSessionHasNoErrors();
        $note = CreditNote::latest('id')->firstOrFail();
        $this->post(route('credit-notes.open', $note))->assertSessionHas('success');

        // Stock: 6 + 2 bought + 2 back = 10, the 2 back at 1,000 each.
        $this->assertSame(10.0, $this->stock($item));
        $layer = InventoryLayer::where('reference_type', CreditNote::class)->where('reference_id', $note->id)->firstOrFail();
        $this->assertEquals(2, (float) $layer->remaining_quantity);
        $this->assertEquals(1000, (float) $layer->unit_cost);

        // Dr revenue 3,000, Dr VAT 225, Cr receivables 3,225; Dr inventory 2,000, Cr COGS 2,000.
        $this->assertSame(['1200' => [0.0, 3225.0], '1300' => [2000.0, 0.0], '2400' => [225.0, 0.0], '4000' => [3000.0, 0.0], '5000' => [0.0, 2000.0]],
            collect($this->journalLines(CreditNote::class, $note->id))->sortKeys()->all());
        $this->assertSame(6000.0 + 3200.0 + 2000.0, $this->balance('1300'));
        $this->assertSame(2000.0, $this->balance('5000'));
        $this->assertSame(-3225.0, $this->balance('1200'), 'the customer is owed 3,225');
        $this->assertBooksBalance();
    }

    public function test_goods_can_only_come_back_once_and_only_from_a_released_invoice(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);
        $line = fn ($qty) => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => $qty, 'unit_price' => 1500, 'tax_rate' => 7.5]];

        $this->note(['invoice_id' => $invoice->id, 'restock' => true, 'items' => $line(3)]);
        $this->assertSame(9.0, $this->stock($item));

        // Only 1 of the 4 is left to credit on that invoice.
        try {
            $this->note(['invoice_id' => $invoice->id, 'restock' => true, 'items' => $line(2)]);
            $this->fail('credited more than the invoice line');
        } catch (ValidationException $e) {
            $this->assertMatchesRegularExpression('/credited up to|left to credit/', collect($e->errors())->flatten()->first());
        }
        $this->assertSame(9.0, $this->stock($item));

        // An unreleased invoice's goods never left the store.
        $unreleased = $this->invoice([['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 7.5]]);
        try {
            $this->note(['invoice_id' => $unreleased->id, 'restock' => true, 'items' => $line(1)]);
            $this->fail('returned goods that never left');
        } catch (ValidationException $e) {
            $this->assertStringContainsString("haven't left your stock", collect($e->errors())->flatten()->first());
        }
        $this->assertSame(9.0, $this->stock($item));

        // Restock needs a stock item.
        $this->expectException(ValidationException::class);
        $this->note(['restock' => true, 'items' => [['description' => 'Delivery fee', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0]]]);
    }

    public function test_a_credit_note_from_an_invoice_cannot_credit_more_than_each_line(): void
    {
        $invoice = $this->invoice([
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 50000, 'tax_rate' => 7.5],
            ['description' => 'Training', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5],
        ]);
        $tooMany = ['invoice_id' => $invoice->id, 'items' => [['description' => 'Consulting', 'quantity' => 3, 'unit_price' => 1000, 'tax_rate' => 7.5]]];
        $tooMuch = ['invoice_id' => $invoice->id, 'items' => [['description' => 'Training', 'quantity' => 1, 'unit_price' => 25000, 'tax_rate' => 7.5]]];
        $notOnIt = ['invoice_id' => $invoice->id, 'items' => [['description' => 'Something else', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 7.5]]];

        foreach (['quantity' => $tooMany, 'amount' => $tooMuch, 'line' => $notOnIt] as $what => $data) {
            try {
                $this->note($data);
                $this->fail("too much {$what} credited");
            } catch (ValidationException) {
                // expected
            }
        }

        // A price reduction within the line is fine.
        $ok = $this->note(['invoice_id' => $invoice->id, 'items' => [['description' => 'Training', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 7.5]]]);
        $this->assertSame('open', $ok->status);
        $this->assertSame(1, CreditNote::count());
    }

    public function test_the_create_screen_from_an_invoice_fills_in_what_is_left_to_credit(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->invoice([
            ['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 4, 'unit_price' => 1500, 'tax_rate' => 7.5],
            ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
        ]);
        $this->note(['invoice_id' => $invoice->id, 'items' => [['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]]);

        $r = $this->get(route('credit-notes.create', ['invoice_id' => $invoice->id]))->assertOk()
            ->assertSee($invoice->invoice_number)->assertSee('The customer returned the goods');
        $lines = $r->viewData('lines');
        $this->assertCount(1, $lines, 'the delivery line is fully credited');
        $this->assertSame(4.0, $lines[0]['quantity']);
        $this->assertSame(1500.0, $lines[0]['unit_price']);
    }

    // ---- apply, refund, void, delete ------------------------------------

    public function test_applying_a_credit_lowers_the_invoice_balance_and_closes_the_credit_when_used_up(): void
    {
        $invoice = $this->invoice([['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5]]); // 21,500
        $note = $this->serviceNote(10000); // 10,750
        $journals = Journal::count();

        $this->post(route('credit-notes.apply.store', $note), ['invoice_id' => $invoice->id, 'amount' => 4000])->assertSessionHas('success');
        $invoice->refresh();
        $this->assertEquals(17500, (float) $invoice->balance_due);
        $this->assertSame('partial', $invoice->status);
        $this->assertEquals(6750, (float) $note->fresh()->balance);
        $this->assertSame($journals, Journal::count(), 'applying posts nothing: both sides are receivables');

        // Not more than is left on the credit note.
        $this->post(route('credit-notes.apply.store', $note), ['invoice_id' => $invoice->id, 'amount' => 7000])->assertSessionHas('error');
        $this->assertEquals(6750, (float) $note->fresh()->balance);

        app(ApplyCreditNote::class)->handle($note, $invoice, 6750);
        $this->assertSame('closed', $note->fresh()->status);
        $this->assertEquals(0, (float) $note->fresh()->balance);
        $this->assertEquals(10750, (float) $invoice->fresh()->balance_due);
        $this->assertSame(10750.0, $this->balance('1200'));
        $this->assertBooksBalance();

        // The invoice page shows the credit; the customer page shows no unused credit.
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($note->credit_note_number)->assertSee('Create credit note');
        $this->get(route('customers.show', $this->customer))->assertOk()->assertSee('Unused credit')->assertDontSee($note->credit_note_number);
    }

    public function test_a_refund_pays_the_customer_from_the_bank_and_clears_the_credit(): void
    {
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 50000]);
        $note = $this->serviceNote(10000); // 10,750 owed to the customer
        $this->get(route('customers.show', $this->customer))->assertOk()->assertSee($note->credit_note_number);

        $this->post(route('credit-notes.refund', $note), [
            'refund_date' => '2026-10-12', 'amount' => 750, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->post(route('credit-notes.refund', $note), [
            'refund_date' => '2026-10-12', 'amount' => 10000, 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id, 'reference' => 'TRF-1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('closed', $note->fresh()->status);
        $this->assertEquals(40000, (float) $bank->fresh()->current_balance);
        $refund = CreditNoteRefund::where('bank_id', $bank->id)->firstOrFail();
        $lines = $this->journalLines(CreditNoteRefund::class, $refund->id);
        $this->assertSame([10000.0, 0.0], $lines['1200']);
        $this->assertSame(10000.0, collect($lines)->except('1200')->sum(fn ($l) => $l[1]));
        $this->assertSame(0.0, $this->balance('1200'), 'the credit is paid out');
        $this->assertSame(-750.0, $this->balance('1000'), 'cash paid out');

        // Nothing left to refund.
        $this->post(route('credit-notes.refund', $note), ['refund_date' => '2026-10-12', 'amount' => 1, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->assertBooksBalance();
    }

    public function test_voiding_reverses_the_journal_and_takes_the_returned_goods_back_out(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);
        $before = ['1200' => $this->balance('1200'), '1300' => $this->balance('1300'), '4000' => $this->balance('4000'), '5000' => $this->balance('5000'), '2400' => $this->balance('2400')];

        $note = $this->note(['invoice_id' => $invoice->id, 'restock' => true,
            'items' => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 7.5]]]);
        $this->assertSame(8.0, $this->stock($item));

        $this->post(route('credit-notes.void', $note))->assertSessionHas('success');

        $this->assertSame('void', $note->fresh()->status);
        $this->assertSame(6.0, $this->stock($item));
        $this->assertEquals(0, (float) InventoryLayer::where('reference_type', CreditNote::class)->where('reference_id', $note->id)->value('remaining_quantity'));
        foreach ($before as $code => $amount) {
            $this->assertSame($amount, $this->balance($code), "account {$code} back as it was");
        }
        $this->assertSame(2, Journal::where('reference_type', CreditNote::class)->where('reference_id', $note->id)->count(), 'original kept, reversal added');
        $this->assertBooksBalance();

        // A void credit note can't be voided, used or deleted.
        $this->post(route('credit-notes.void', $note))->assertSessionHas('error');
        $this->delete(route('credit-notes.destroy', $note))->assertSessionHas('error');
        $this->assertNotNull(CreditNote::find($note->id));
    }

    public function test_voiding_after_some_returned_goods_were_sold_again_takes_them_from_other_stock(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);
        $note = $this->note(['invoice_id' => $invoice->id, 'restock' => true,
            'items' => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 7.5]]]);
        $this->assertSame(8.0, $this->stock($item));

        // 7 more sold: the average-cost sale takes a share of the returned layer too.
        $second = $this->invoice([['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 7, 'unit_price' => 1500, 'tax_rate' => 0]]);
        $this->assertLessThan(2, (float) InventoryLayer::where('reference_type', CreditNote::class)->where('reference_id', $note->id)->value('remaining_quantity'));

        // Only 1 is free (7 are reserved for the new invoice): can't void.
        $this->post(route('credit-notes.void', $note))->assertSessionHas('error');
        $this->assertSame('open', $note->fresh()->status);

        // Once that invoice is cancelled the goods are free again and it can be voided.
        $second->update(['status' => 'cancelled']);
        $second->releaseInventoryReservation();
        $this->post(route('credit-notes.void', $note))->assertSessionHas('success');
        $this->assertSame(6.0, $this->stock($item));
        $layerValue = (float) InventoryLayer::where('item_id', $item->id)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v');
        $this->assertEqualsWithDelta($this->balance('1300'), $layerValue, 0.01, 'stock layers agree with the inventory account');
        $this->assertBooksBalance();
    }

    public function test_a_used_credit_cannot_be_voided_and_only_a_draft_can_be_deleted(): void
    {
        $invoice = $this->invoice([['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5]]);
        $note = $this->serviceNote(1000);
        app(ApplyCreditNote::class)->handle($note, $invoice, 100);

        $this->post(route('credit-notes.void', $note))->assertSessionHas('error');
        $this->assertSame('open', $note->fresh()->status);
        $this->delete(route('credit-notes.destroy', $note))->assertSessionHas('error');

        $draft = $this->serviceNote(1000, 'draft');
        $this->delete(route('credit-notes.destroy', $draft))->assertRedirect(route('credit-notes.index'));
        $this->assertNull(CreditNote::find($draft->id));
        $this->assertSame(0, Journal::where('reference_type', CreditNote::class)->where('reference_id', $draft->id)->count());
    }

    public function test_the_status_enum_refuses_moves_it_does_not_allow(): void
    {
        $note = $this->serviceNote(1000);
        app(VoidCreditNote::class)->handle($note);

        $this->expectException(ValidationException::class);
        $note->fresh()->update(['status' => 'open']);
    }

    // ---- VAT return ----------------------------------------------------

    public function test_the_vat_return_still_reconciles_with_a_credit_note_in_the_month(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);                  // 6,000 + 450 VAT
        $this->invoice([['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 7.5]]); // 7,500 VAT
        $note = $this->note(['invoice_id' => $invoice->id, 'restock' => true, 'credit_note_date' => '2026-10-12',
            'items' => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 7.5]]]);
        app(RefundCreditNote::class)->handle($note, ['refund_date' => '2026-10-14', 'amount' => 3225, 'payment_method' => 'cash'], $this->user->id);
        $voided = $this->serviceNote(2000);                          // posted and reversed in the same month
        app(VoidCreditNote::class)->handle($voided);

        $r = $this->get(route('reports.vat-return', ['month' => '2026-10']))->assertOk();
        $L = $r->viewData('lines');
        $this->assertEqualsWithDelta(7500 + 450 - 225, $L[45], 0.001, 'output VAT less the credit note');

        $rec = $r->viewData('reconciliation')['output'];
        $this->assertEqualsWithDelta(7725, $rec['documents'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['other'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['unexplained'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['difference'], 0.001);

        $credit = $r->viewData('adjustmentsSchedule')->where('document', 'Credit note')->where('document_id', $note->id);
        $this->assertEqualsWithDelta(-3000, $credit->sum('net'), 0.001);
        $this->assertEqualsWithDelta(-225, $credit->sum('vat'), 0.001);
        $this->assertSame('standard', $credit->first()->treatment);
    }

    public function test_credit_note_lines_take_the_vat_treatment_of_the_invoice_line(): void
    {
        $invoice = $this->invoice([
            ['description' => 'Rice (zero-rated)', 'quantity' => 2, 'unit_price' => 5000, 'tax_rate' => 0, 'vat_treatment' => 'zero'],
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 7.5],
        ]);
        $note = $this->note(['invoice_id' => $invoice->id, 'items' => [
            ['description' => 'Rice (zero-rated)', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5],
        ]]);
        $standalone = $this->note(['items' => [['description' => 'Deposit returned', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'vat_treatment' => 'out_of_scope']]]);

        $this->assertSame(['zero', 'standard'], $note->items->sortBy('id')->pluck('vat_treatment')->all());
        $this->assertSame('out_of_scope', $standalone->items->first()->vat_treatment);
    }

    // ---- screens, tenants, permissions ---------------------------------

    public function test_the_screens_render(): void
    {
        $item = $this->stockedItem();
        $invoice = $this->soldAndDelivered($item);
        $note = $this->note(['invoice_id' => $invoice->id, 'restock' => true,
            'items' => [['item_id' => $item->id, 'description' => 'Rice bag', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 7.5]]]);
        $draft = $this->serviceNote(1000, 'draft');

        $this->get(route('credit-notes.index'))->assertOk()->assertSee('Credit notes')->assertSeeLivewire('credit-notes.credit-notes-table');
        Livewire::test(CreditNotesTable::class)
            ->assertSee($note->credit_note_number)->assertSee($draft->credit_note_number)
            ->set('tab', 'draft')->assertDontSee($note->credit_note_number)->assertSee($draft->credit_note_number);
        $this->get(route('credit-notes.show', $note))->assertOk()
            ->assertSee('Apply to an invoice')->assertSee('Refund to the customer')->assertSee('Back in stock at cost')->assertSee('Ledger postings');
        $this->get(route('credit-notes.show', $draft))->assertOk()->assertSee('Post it');
        $this->get(route('credit-notes.edit', $draft))->assertOk()->assertSee('Consulting');
        $this->get(route('credit-notes.edit', $note))->assertRedirect(route('credit-notes.show', $note));
        $this->get(route('credit-notes.create'))->assertOk()->assertSee('Save and post');
        $this->get(route('credit-notes.print', $note))->assertOk()->assertSee('CREDIT NOTE')->assertSee($invoice->invoice_number);
        $pdf = $this->get(route('credit-notes.pdf', $note))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->put(route('credit-notes.update', $draft), [
            'customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-11',
            'items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 1000, 'tax_rate' => 7.5]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(2150, (float) $draft->fresh()->total);
    }

    public function test_another_business_cannot_see_or_touch_a_credit_note(): void
    {
        $note = $this->serviceNote(1000);

        auth()->logout();
        [$otherTenant] = $this->createTenantWithSubscription();
        $outsider = $this->createUserForTenant($otherTenant, ['view invoices', 'create invoices', 'edit invoices', 'delete invoices']);
        $this->actingAs($outsider);

        $this->assertNull(CreditNote::find($note->id));
        $this->assertSame(0, CreditNote::count());
        $this->get(route('credit-notes.show', $note))->assertNotFound();
        $this->get(route('credit-notes.pdf', $note))->assertNotFound();
        $this->post(route('credit-notes.void', $note))->assertNotFound();
        $this->post(route('credit-notes.refund', $note), ['refund_date' => '2026-10-12', 'amount' => 10, 'payment_method' => 'cash'])->assertNotFound();
        $this->delete(route('credit-notes.destroy', $note))->assertNotFound();
        // Nor raise one for the first business's customer.
        $this->post(route('credit-notes.store'), [
            'customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-12',
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 0]],
        ])->assertSessionHasErrors('customer_id');

        $this->assertSame('open', CreditNote::withoutGlobalScopes()->find($note->id)->status);
        $this->assertSame(0, CreditNoteRefund::withoutGlobalScopes()->count());
    }

    public function test_permissions_follow_the_invoice_permissions(): void
    {
        $note = $this->serviceNote(1000);
        $draft = $this->serviceNote(1000, 'draft');

        auth()->logout();
        $viewer = $this->createUserForTenant($this->tenant, ['view invoices']);
        $this->actingAs($viewer);
        $this->get(route('credit-notes.index'))->assertOk()->assertDontSee('New credit note');
        $this->get(route('credit-notes.show', $note))->assertOk()->assertDontSee('Record refund')->assertDontSee('Void');
        $this->get(route('credit-notes.print', $note))->assertOk();
        $this->get(route('credit-notes.create'))->assertForbidden();
        $this->post(route('credit-notes.store'), [])->assertForbidden();
        $this->post(route('credit-notes.open', $draft))->assertForbidden();
        $this->post(route('credit-notes.void', $note))->assertForbidden();
        $this->post(route('credit-notes.refund', $note), ['refund_date' => '2026-10-12', 'amount' => 10, 'payment_method' => 'cash'])->assertForbidden();
        $this->delete(route('credit-notes.destroy', $draft))->assertForbidden();

        auth()->logout();
        $this->actingAs($this->createUserForTenant($this->tenant, ['view customers']));
        $this->get(route('credit-notes.index'))->assertForbidden();
        $this->assertSame('open', $note->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_the_feature_switch_hides_the_screens(): void
    {
        $this->assertTrue(config('mybooks.features.credit_notes'), 'on by default now it is finished');
        $this->get(route('invoices.index'))->assertOk()->assertSee('href="'.route('credit-notes.index').'"', false);

        config(['mybooks.features.credit_notes' => false]);
        $this->get(route('credit-notes.index'))->assertNotFound();
        $this->get(route('invoices.index'))->assertOk()->assertDontSee('href="'.route('credit-notes.index').'"', false);
    }
}
