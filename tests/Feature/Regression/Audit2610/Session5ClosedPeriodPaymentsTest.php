<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Bills\SaveBill;
use App\Actions\CreditNotes\ApplyCreditNote;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\Payments\RecordPaymentReceived;
use App\Actions\VendorCredits\ApplyVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Models\AccountingPeriod;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 5 (MB-01): paying or crediting an invoice
 * or bill dated in a closed or locked month failed, because saving its new
 * balance re-posted the document's journal, which the lock refuses. A
 * payment must leave the document's posted journal exactly as it was.
 */
class Session5ClosedPeriodPaymentsTest extends TestCase
{
    private Customer $customer;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(['view invoices', 'create invoices', 'edit invoices', 'view bills', 'create bills']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public static function closures(): array
    {
        return ['closed period' => ['period'], 'lock date' => ['lock']];
    }

    /** Close September one way or the other. */
    private function closeSeptember(string $how): void
    {
        if ($how === 'period') {
            AccountingPeriod::create([
                'tenant_id' => $this->tenant->id, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
                'status' => 'closed', 'closed_at' => now(), 'fiscal_year' => 2026,
            ]);

            return;
        }
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Filed']);
    }

    private function invoice(): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-09-15', 'due_date' => '2026-12-31', 'status' => 'sent',
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    private function bill(): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-09-15', 'due_date' => '2026-12-31',
            'items' => [['description' => 'Repairs', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    /** @return array<int, array{0: int, 1: string, 2: string, 3: string}> the journal's lines as they stand */
    private function journalLines(string $type, int $id): array
    {
        return Journal::where('reference_type', $type)->where('reference_id', $id)->sole()
            ->entries()->orderBy('id')->get()
            ->map(fn ($e) => [$e->id, (string) $e->account_id, number_format((float) $e->debit, 2), number_format((float) $e->credit, 2)])->all();
    }

    #[DataProvider('closures')]
    public function test_an_invoice_from_a_closed_month_can_be_paid(string $how): void
    {
        $invoice = $this->invoice();
        $before = $this->journalLines(Invoice::class, $invoice->id);
        $this->closeSeptember($how);

        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-05', 'amount' => 4000, 'payment_method' => 'cash',
        ], $this->user->id);

        $invoice->refresh();
        $this->assertSame('6750.00', number_format((float) $invoice->balance_due, 2, '.', ''));
        $this->assertSame('partial', $invoice->status);
        $this->assertSame($before, $this->journalLines(Invoice::class, $invoice->id));
    }

    #[DataProvider('closures')]
    public function test_a_credit_note_can_be_applied_to_an_invoice_from_a_closed_month(string $how): void
    {
        $invoice = $this->invoice();
        $before = $this->journalLines(Invoice::class, $invoice->id);
        $this->closeSeptember($how);
        $note = app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-05', 'reason' => 'product_return', 'status' => 'open',
            'items' => [['description' => 'Refund', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5]],
        ], $this->user->id);

        app(ApplyCreditNote::class)->handle($note, $invoice, 1075, '2026-10-05');

        $this->assertSame('9675.00', number_format((float) $invoice->fresh()->balance_due, 2, '.', ''));
        $this->assertSame($before, $this->journalLines(Invoice::class, $invoice->id));
    }

    #[DataProvider('closures')]
    public function test_a_bill_from_a_closed_month_can_be_paid(string $how): void
    {
        $bill = $this->bill();
        $before = $this->journalLines(Bill::class, $bill->id);
        $this->closeSeptember($how);

        app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-10-05', 'amount' => 10750, 'payment_method' => 'cash',
        ], $this->user->id);

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame($before, $this->journalLines(Bill::class, $bill->id));
    }

    #[DataProvider('closures')]
    public function test_a_supplier_credit_can_be_applied_to_a_bill_from_a_closed_month(string $how): void
    {
        $bill = $this->bill();
        $before = $this->journalLines(Bill::class, $bill->id);
        $this->closeSeptember($how);
        $credit = app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'credit_date' => '2026-10-05', 'status' => 'open', 'reason' => 'goods_returned',
            'items' => [['description' => 'Returned part', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5]],
        ], $this->user->id);

        app(ApplyVendorCredit::class)->handle($credit, $bill, 1075, '2026-10-05');

        $this->assertSame('9675.00', number_format((float) $bill->fresh()->balance_due, 2, '.', ''));
        $this->assertSame($before, $this->journalLines(Bill::class, $bill->id));
    }

    #[DataProvider('closures')]
    public function test_editing_an_invoice_from_a_closed_month_is_still_refused(string $how): void
    {
        $invoice = $this->invoice();
        $this->closeSeptember($how);

        $this->expectException(ValidationException::class);
        app(SaveInvoice::class)->update($invoice, ['items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]]]);
    }

    public function test_an_edit_that_keeps_the_totals_still_re_posts_the_journal(): void
    {
        $invoice = $this->invoice();
        $other = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Musa & Sons']);

        // Same lines and totals, different customer: the journal must follow.
        app(SaveInvoice::class)->update($invoice, ['customer_id' => $other->id]);

        $journal = Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->sole();
        $this->assertStringContainsString('Musa & Sons', $journal->description);
    }
}
