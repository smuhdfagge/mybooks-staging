<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\SalesReceipts\SaveSalesReceipt;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\SalesReceipt;
use App\Models\Vendor;
use App\Services\Accounting\VatReturnForm;
use App\Services\Statements\Subledger;
use Tests\TestCase;

/**
 * Follow-up to session 17 (MB-15, reverse and re-post on edit), from its
 * review: places that took "the" journal of a document, and a no-change
 * edit of a stock sale that re-costed it.
 */
class Session17ReviewFixesTest extends TestCase
{
    private Customer $customer;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
        $this->createAuthenticatedUser(['view invoices', 'create invoices', 'edit invoices', 'view bills', 'create bills', 'view sales-receipts', 'create sales-receipts', 'edit sales-receipts']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function invoice(string $date, array $line = ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5]): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => $date, 'due_date' => '2026-12-31', 'status' => 'sent', 'items' => [$line],
        ], $this->user->id);
    }

    private function buy(Item $item, float $quantity, float $cost, string $date): void
    {
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity, 'unit_price' => $cost, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    private function cogs(Journal $journal): float
    {
        return (float) $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '5000'))->sum('debit');
    }

    public function test_the_vat_return_finds_the_journal_a_reversal_reversed(): void
    {
        // September, moved to October, then doubled: the second reversal is of
        // the October journal, not of the first (September) one.
        $invoice = $this->invoice('2026-09-25');
        app(SaveInvoice::class)->update($invoice, ['invoice_date' => '2026-10-05']);
        app(SaveInvoice::class)->update($invoice->fresh(), ['items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);

        $october = app(VatReturnForm::class)->build($this->tenant->id, '2026-10');
        $this->assertEqualsWithDelta(20000, $october['lines'][20], 0.001, 'October supplies: the invoice as it is now');
        $this->assertEqualsWithDelta(0, $october['lines'][35], 0.001, 'no sales adjustment in October');
    }

    public function test_an_edited_invoice_cancelled_before_its_date_comes_off_the_statement_on_its_date(): void
    {
        // Dated 15 November, edited, then cancelled on 20 October.
        $invoice = $this->invoice('2026-11-15');
        app(SaveInvoice::class)->update($invoice, ['items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);
        $invoice->fresh()->update(['status' => 'cancelled']);

        // Not on the edit's reversal date, and not before the invoice either:
        // the books never show receivables below zero for it.
        $statement = app(Subledger::class)->movements($this->tenant->id, Subledger::CUSTOMERS, $this->customer->id);
        $this->assertSame([['invoice', '2026-11-15'], ['reversal', '2026-11-15']], $statement->map(fn ($m) => [$m->kind, $m->date])->all());
        $this->assertSame([], array_filter(app(Subledger::class)->balances($this->tenant->id, Subledger::CUSTOMERS, '2026-10-31')),
            'October closes at nothing, as November opens');
        $this->assertSame('2026-11-15', Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->latest('id')->first()->journal_date->toDateString());
    }

    public function test_a_no_change_edit_of_a_stock_sale_keeps_its_cost_and_its_journal(): void
    {
        $rice = Item::factory()->product()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rice', 'valuation_method' => 'weighted_average']);
        $this->buy($rice, 10, 1000, '2026-10-01');
        $line = ['item_id' => $rice->id, 'description' => 'Rice', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 0];
        $invoice = $this->invoice('2026-10-05', $line);
        $receipt = app(SaveSalesReceipt::class)->create($this->tenant->id, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'items' => [['quantity' => 1] + $line],
        ], $this->user->id);
        $invoiceJournal = Journal::currentFor($invoice);
        $receiptJournal = Journal::currentFor($receipt);
        $this->assertEqualsWithDelta(2000, $this->cogs($invoiceJournal), 0.001);

        // Dearer stock arrives after both sales.
        $this->buy($rice, 10, 2000, '2026-10-10');

        app(SaveInvoice::class)->update($invoice->fresh(), ['notes' => 'Delivered to the Kano shop']);
        app(SaveSalesReceipt::class)->update($receipt->fresh(), [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'notes' => 'Walk-in', 'items' => [['quantity' => 1] + $line],
        ]);

        $this->assertSame([$invoiceJournal->id], Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->pluck('id')->all());
        $this->assertSame([$receiptJournal->id], Journal::where('reference_type', SalesReceipt::class)->where('reference_id', $receipt->id)->pluck('id')->all());
        $this->assertEqualsWithDelta(1000, (float) $invoice->fresh()->items()->sole()->unit_cost, 0.0001, 'still costed at what was in stock on 5 October');

        // Changing the stock on it still re-issues and re-posts.
        app(SaveInvoice::class)->update($invoice->fresh(), ['items' => [['quantity' => 3] + $line]]);
        $this->assertNotSame($invoiceJournal->id, Journal::currentFor($invoice)->id);
    }

    public function test_stock_from_an_item_no_longer_kept_in_stock_goes_back_on_the_next_edit(): void
    {
        $rice = Item::factory()->product()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rice']);
        $oil = Item::factory()->product()->create(['tenant_id' => $this->tenant->id, 'name' => 'Oil']);
        $this->buy($rice, 10, 1000, '2026-10-01');
        $this->buy($oil, 10, 500, '2026-10-01');
        $lines = [
            ['item_id' => $rice->id, 'description' => 'Rice', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 0],
            ['item_id' => $oil->id, 'description' => 'Oil', 'quantity' => 4, 'unit_price' => 800, 'tax_rate' => 0],
        ];
        $receipt = app(SaveSalesReceipt::class)->create($this->tenant->id, ['receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'items' => $lines], $this->user->id);
        $onHand = fn (Item $item) => (float) Inventory::withoutGlobalScopes()->where('item_id', $item->id)->sum('quantity');
        $this->assertEqualsWithDelta(6, $onHand($oil), 0.001);

        // Oil stops being kept in stock; the receipt is then edited without changing its figures.
        $oil->update(['track_inventory' => false]);
        app(SaveSalesReceipt::class)->update($receipt->fresh(), ['receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'notes' => 'Walk-in', 'items' => $lines]);

        // The journal no longer takes oil out of stock, so the stock records give it back too.
        $this->assertEqualsWithDelta(10, $onHand($oil), 0.001);
        $this->assertEqualsWithDelta(8, $onHand($rice), 0.001);
        $this->assertEqualsWithDelta(2000, $this->cogs(Journal::currentFor($receipt)), 0.001, 'only the rice');
    }
}
