<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\CreditNotes\ApplyCreditNote;
use App\Actions\CreditNotes\RefundCreditNote;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\Payments\ApplySupplierAdvance;
use App\Actions\Payments\DeletePaymentReceived;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\Payments\RecordPaymentReceived;
use App\Actions\VendorCredits\ApplyVendorCredit;
use App\Actions\VendorCredits\RefundVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\WhtCategory;
use App\Notifications\StatementNotification;
use App\Services\Statements\Statement;
use App\Services\Statements\StatementBuilder;
use Carbon\Carbon;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Session 10: customer and supplier statements (activity and open items),
 * PDF, email and bulk email, and the balance shown on the customer and
 * supplier pages.
 */
class StatementsTest extends TestCase
{
    protected Customer $customer;

    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser([
            'view customers', 'view vendors', 'view reports', 'send invoices', 'view invoices', 'create invoices', 'edit invoices',
            'create payments-received', 'delete payments-received', 'create bills', 'view bills', 'create payments-made', 'delete payments-made',
            'create fixed-assets', 'view fixed-assets',
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aminu Stores', 'email' => 'aminu@example.com']);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dangote Supplies', 'email' => 'accounts@dangote.example']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function invoice(float $amount, string $date, string $due, string $status = 'unpaid', ?Customer $customer = null): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => ($customer ?? $this->customer)->id, 'invoice_date' => $date, 'due_date' => $due, 'status' => $status,
            'items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /** @param array<string, mixed> $extra */
    private function pay(?Invoice $invoice, float $amount, string $date, array $extra = [], ?Customer $customer = null): PaymentReceived
    {
        return app(RecordPaymentReceived::class)->handle($this->tenant->id, $extra + [
            'customer_id' => ($customer ?? $this->customer)->id, 'invoice_id' => $invoice?->id, 'payment_date' => $date,
            'amount' => $amount, 'payment_method' => 'cash',
        ], $this->user->id);
    }

    private function creditNote(float $amount, string $date): CreditNote
    {
        return app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'credit_note_date' => $date, 'reason' => 'price_adjustment', 'status' => 'open',
            'items' => [['description' => 'Discount agreed', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /**
     * Every kind of customer document (see the class doc on Subledger):
     *   A  10 Aug  10,000 due 25 Aug, paid 2 Oct: 9,500 cash + 500 WHT
     *   B   5 Sep  20,000 due 20 Sep, credit note 2,000 (16 Sep) and deposit 1,500 (20 Oct) used on it
     *   C   5 Oct   5,000 due 5 Nov, a 2,000 payment (3 Oct) deleted today
     *   D  draft   7,000 (not on the account)
     *   E  20 Sep   6,000 cancelled today
     *   CN1 15 Sep 3,000: 2,000 used on B, 500 refunded 10 Oct, 500 left
     *   Deposit 1 Sep 4,000: 1,500 used on B, 2,500 left
     *   Payment 12 Oct 1,000 not linked to an invoice
     * Balance: 35,000 invoiced - 10,000 - 3,000 + 500 - 4,000 - 1,000 = 17,500.
     */
    private function customerScenario(): void
    {
        $a = $this->invoice(10000, '2026-08-10', '2026-08-25');
        $b = $this->invoice(20000, '2026-09-05', '2026-09-20');
        $this->invoice(7000, '2026-09-10', '2026-10-10', 'draft');
        $e = $this->invoice(6000, '2026-09-20', '2026-10-20');

        $deposit = $this->pay(null, 4000, '2026-09-01', ['is_deposit' => true]);
        $note = $this->creditNote(3000, '2026-09-15');
        app(ApplyCreditNote::class)->handle($note, $b, 2000, '2026-09-16');

        $this->pay($a, 9500, '2026-10-02', ['wht_amount' => 500]);
        $c = $this->invoice(5000, '2026-10-05', '2026-11-05');
        $p2 = $this->pay($c, 2000, '2026-10-03');
        app(RefundCreditNote::class)->handle($note->fresh(), ['refund_date' => '2026-10-10', 'amount' => 500, 'payment_method' => 'cash'], $this->user->id);
        $this->pay(null, 1000, '2026-10-12');
        $this->pay($b, 1500, '2026-10-20', ['apply_deposit_id' => $deposit->id, 'deposit_amount' => 1500]);

        $e->update(['status' => 'cancelled']);
        app(DeletePaymentReceived::class)->handle($p2->fresh());
    }

    private function statement(Customer|Vendor $party, string $type, ?string $from, string $to): Statement
    {
        return app(StatementBuilder::class)->build($party, $type, $from, $to);
    }

    // ---- customer statement --------------------------------------------

    public function test_customer_balance_agrees_everywhere_with_every_kind_of_document(): void
    {
        $this->customerScenario();

        $statement = $this->statement($this->customer, Statement::ACTIVITY, '2026-01-01', '2026-10-20');
        $this->assertEqualsWithDelta(17500, $statement->closing, 0.001);

        // The customer page: same balance; unpaid invoices less unused credits.
        $customer = $this->customer->fresh();
        $this->assertEqualsWithDelta(21500, (float) $customer->outstanding_balance, 0.001, 'B 16,500 + C 5,000; no draft, no cancelled');
        $this->assertEqualsWithDelta(2500, (float) $customer->deposit_balance, 0.001);
        $openCredits = (float) CreditNote::where('customer_id', $customer->id)->where('status', 'open')->sum('balance');
        $this->assertEqualsWithDelta(500, $openCredits, 0.001);
        $this->assertEqualsWithDelta(17500, $customer->outstanding_balance - $customer->deposit_balance - $openCredits - 1000, 0.001);
        $this->assertEqualsWithDelta(17500, app(StatementBuilder::class)->balance($customer), 0.001);
        $this->get(route('customers.show', $customer))->assertOk()
            ->assertViewHas('balanceOwed', fn ($v) => abs($v - 17500) < 0.001)
            ->assertSee('Balance owed')->assertSee('17,500.00');

        // Open items as at today: the same total, and the invoices agree with aged receivables.
        $open = $this->statement($this->customer, Statement::OPEN_ITEMS, null, '2026-10-20');
        $this->assertEqualsWithDelta(17500, $open->closing, 0.001);
        $items = collect($open->open['items'])->pluck('amount', 'reference')->all();
        $this->assertCount(2, $items);
        $this->assertEqualsWithDelta(16500, array_values($items)[0], 0.001);
        $this->assertEqualsWithDelta(5000, array_values($items)[1], 0.001);
        $this->assertEqualsWithDelta(4000, collect($open->open['credits'])->sum('amount'), 0.001, 'credit note 500 + deposit 2,500 + payment 1,000');
        $aged = $this->get(route('reports.accounts-receivable', ['as_of' => '2026-10-20']))->assertOk()
            ->viewData('invoices')->where('customer_id', $this->customer->id);
        $this->assertEqualsWithDelta(array_sum($items), (float) $aged->sum('balance_due'), 0.001);
        $this->assertEqualsWithDelta($open->ageing['total'], $open->closing, 0.001);
    }

    public function test_activity_statement_has_opening_balance_running_balance_and_every_line(): void
    {
        $this->customerScenario();

        $s = $this->statement($this->customer, Statement::ACTIVITY, '2026-10-01', '2026-10-20');

        // Before 1 Oct: A 10,000 + B 20,000 + E 6,000 - CN 3,000 - deposit 4,000.
        $this->assertEqualsWithDelta(29000, $s->opening, 0.001);
        $this->assertEqualsWithDelta(17500, $s->closing, 0.001);
        $this->assertEqualsWithDelta($s->closing, collect($s->rows)->last()['balance'], 0.001);
        $this->assertEqualsWithDelta($s->opening + $s->totalCharges - $s->totalCredits, $s->closing, 0.001);

        $labels = collect($s->rows)->pluck('label')->implode(' | ');
        $this->assertStringContainsString('Withholding tax you deducted', $labels);
        $this->assertStringContainsString('Refund paid to you from credit note', $labels);
        $this->assertStringContainsString('(not linked to an invoice)', $labels);
        $this->assertStringContainsString('cancelled', $labels);
        $this->assertStringContainsString('deleted', $labels);
        $this->assertStringNotContainsString('Deposit used', $labels, 'using a deposit on an invoice does not change the balance');
        $this->assertSame(['2026-10-02', '2026-10-02'], collect($s->rows)->take(2)->pluck('date')->all(), 'payment then its WHT, same day');

        // Whole year: the draft invoice is nowhere.
        $year = $this->statement($this->customer, Statement::ACTIVITY, '2026-01-01', '2026-10-20');
        $this->assertSame(0.0, $year->opening);
        $this->assertCount(0, collect($year->rows)->filter(fn ($r) => str_contains($r['label'], 'INV') && $r['charge'] == 7000));
    }

    public function test_open_items_on_a_past_date_use_what_was_true_then(): void
    {
        $this->customerScenario();

        $s = $this->statement($this->customer, Statement::OPEN_ITEMS, null, '2026-09-30');

        // A 10,000 (due 25 Aug), B 18,000 (credit note used, deposit not yet), E 6,000 (cancelled later).
        $items = collect($s->open['items']);
        $this->assertSame([10000.0, 18000.0, 6000.0], $items->pluck('amount')->all());
        $this->assertSame([36, 10, 0], $items->pluck('days_overdue')->all());
        $this->assertEqualsWithDelta(5000, collect($s->open['credits'])->sum('amount'), 0.001, 'credit note 1,000 left + deposit 4,000');
        $this->assertEqualsWithDelta(29000, $s->closing, 0.001);
        $this->assertEquals(['current' => 6000.0, '1_30' => 18000.0, '31_60' => 10000.0, '61_90' => 0.0, 'over_90' => 0.0, 'credits' => -5000.0, 'total' => 29000.0], $s->ageing);
    }

    public function test_statement_page_print_and_pdf(): void
    {
        $this->customerScenario();

        $this->get(route('customers.show', $this->customer))->assertSee(route('customers.statement', $this->customer));

        $this->get(route('customers.statement', [$this->customer, 'type' => 'activity', 'from' => '2026-10-01', 'to' => '2026-10-20']))
            ->assertOk()->assertSee('Balance brought forward')->assertSee('29,000.00')->assertSee('17,500.00')
            ->assertSee('Email statement')->assertSee('Download PDF')->assertSee('Not yet due');
        $this->get(route('customers.statement', [$this->customer, 'type' => 'open', 'to' => '2026-10-20']))
            ->assertOk()->assertSee('Unpaid invoices')->assertSee('Deposit not yet used');

        $this->get(route('customers.statement.print', [$this->customer, 'type' => 'open', 'to' => '2026-10-20']))
            ->assertOk()->assertSee('STATEMENT OF ACCOUNT')->assertSee('Aminu Stores')->assertSee('Over 90 days');

        $pdf = $this->get(route('customers.statement.pdf', [$this->customer, 'type' => 'activity', 'from' => '2026-10-01', 'to' => '2026-10-20']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('statement-aminu-stores-2026-10-20.pdf', $pdf->headers->get('Content-Disposition'));
    }

    public function test_reports_customer_statement_picks_a_customer(): void
    {
        $this->invoice(1000, '2026-10-01', '2026-10-31');

        $this->get(route('reports.customer-statement'))->assertOk()->assertSee('Choose a customer');
        $this->get(route('reports.customer-statement', ['customer_id' => $this->customer->id, 'from' => '2026-10-01', 'to' => '2026-10-20']))
            ->assertOk()->assertViewHas('statement', fn ($s) => abs($s->closing - 1000) < 0.001);
    }

    /**
     * The old statement took today's balance of older invoices as the
     * opening balance, so a payment in the period for an older invoice was
     * subtracted twice; drafts were counted; WHT and credit notes were not.
     * Fails on the old code (opening 0, closing -5,500 with the draft).
     */
    public function test_old_statement_figures_are_fixed(): void
    {
        $september = $this->invoice(10000, '2026-09-10', '2026-09-30');
        $this->pay($september, 9500, '2026-10-05', ['wht_amount' => 500]);
        $this->invoice(4000, '2026-10-08', '2026-11-08', 'draft');
        $this->creditNote(1000, '2026-10-09');
        $token = $this->user->createToken('test')->plainTextToken;

        $data = $this->withToken($token)
            ->getJson('/api/v1/reports/customer-statement?customer_id='.$this->customer->id.'&start_date=2026-10-01&end_date=2026-10-31')
            ->assertOk()->json('data');

        $this->assertEqualsWithDelta(10000, $data['opening_balance'], 0.001);
        $this->assertEqualsWithDelta(-1000, $data['totals']['closing_balance'], 0.001, 'paid in full, plus a 1,000 credit note');
        $this->assertCount(3, $data['transactions'], 'the payment, its WHT and the credit note; no draft invoice');

        $this->get(route('reports.customer-statement', ['customer_id' => $this->customer->id, 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertViewHas('statement', fn ($s) => abs($s->opening - 10000) < 0.001 && abs($s->closing + 1000) < 0.001);
    }

    public function test_outstanding_balance_leaves_out_draft_and_cancelled_invoices(): void
    {
        // Fails on the old code, which counted every invoice not marked paid.
        $this->invoice(1000, '2026-10-01', '2026-10-31');
        $this->invoice(2000, '2026-10-02', '2026-10-31', 'draft');
        $this->invoice(4000, '2026-10-03', '2026-10-31')->update(['status' => 'cancelled']);

        $this->assertEqualsWithDelta(1000, (float) $this->customer->fresh()->outstanding_balance, 0.001);
        $this->assertEqualsWithDelta(1000, (float) Customer::withBalances()->find($this->customer->id)->outstanding_balance, 0.001);
        $this->assertEqualsWithDelta(1000, app(StatementBuilder::class)->balance($this->customer), 0.001);
    }

    public function test_aged_receivables_counts_sent_invoices(): void
    {
        // Fails on the old code: an emailed ("sent") invoice dropped off the report.
        $invoice = $this->invoice(2500, '2026-10-20', '2026-11-20');
        $invoice->update(['status' => 'sent']);

        $this->get(route('reports.accounts-receivable', ['as_of' => '2026-10-20']))->assertOk()
            ->assertViewHas('totalReceivable', fn ($v) => abs($v - 2500) < 0.001);
    }

    // ---- supplier statement --------------------------------------------

    /**
     *   B1  1 Sep 50,000 due 30 Sep, paid 20 Sep: 47,500 + 2,500 WHT
     *   B2  1 Oct 30,000 due 31 Oct: supplier credit 2,000 and advance 4,000 used on it
     *   VC  5 Oct  5,000: 2,000 used, 1,000 refunded to us, 2,000 left
     *   Advance 2 Oct 10,000: 4,000 used, 6,000 left
     *   Asset bought on account 10 Oct 8,000
     * We owe 80,000 - 50,000 - 5,000 + 1,000 - 10,000 + 8,000 = 24,000.
     */
    private function supplierScenario(): void
    {
        $bill = fn (float $amount, string $date, string $due) => app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => $due,
            'items' => [['description' => 'Supplies', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
        $pay = fn (array $data) => app(RecordPaymentMade::class)->handle($this->tenant->id, $data + [
            'vendor_id' => $this->vendor->id, 'payment_method' => 'cash',
        ], $this->user->id);

        $b1 = $bill(50000, '2026-09-01', '2026-09-30');
        $pay(['bill_id' => $b1->id, 'payment_date' => '2026-09-20', 'amount' => 47500, 'wht_amount' => 2500,
            'wht_category_id' => WhtCategory::where('tenant_id', $this->tenant->id)->value('id')]);
        $b2 = $bill(30000, '2026-10-01', '2026-10-31');
        $advance = $pay(['is_advance' => true, 'payment_date' => '2026-10-02', 'amount' => 10000]);
        $credit = app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'credit_date' => '2026-10-05', 'status' => 'open', 'reason' => 'price_adjustment',
            'items' => [['description' => 'Price reduction', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
        ], $this->user->id);
        app(ApplyVendorCredit::class)->handle($credit, $b2, 2000, '2026-10-06');
        app(RefundVendorCredit::class)->handle($credit->fresh(), ['refund_date' => '2026-10-07', 'amount' => 1000, 'payment_method' => 'cash'], $this->user->id);
        app(ApplySupplierAdvance::class)->handle($advance, $b2->fresh(), 4000, '2026-10-08');
        $this->post(route('fixed-assets.store'), [
            'name' => 'Delivery bike', 'purchase_date' => '2026-10-10', 'in_service_date' => '2026-10-10', 'purchase_cost' => 8000,
            'salvage_value' => 0, 'useful_life' => 5, 'depreciation_method' => 'straight_line', 'funding_source' => 'on_account', 'vendor_id' => $this->vendor->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_supplier_statement_covers_every_kind_of_document(): void
    {
        $this->supplierScenario();

        $s = $this->statement($this->vendor, Statement::ACTIVITY, '2026-10-01', '2026-10-20');
        $this->assertEqualsWithDelta(0, $s->opening, 0.001, 'B1 paid in September');
        $this->assertEqualsWithDelta(24000, $s->closing, 0.001);
        $labels = collect($s->rows)->pluck('label')->implode(' | ');
        $this->assertStringContainsString('Advance', $labels);
        $this->assertStringContainsString('Refund received from you on supplier credit', $labels);
        $this->assertStringContainsString('Asset bought on account: Delivery bike', $labels);

        $september = $this->statement($this->vendor, Statement::ACTIVITY, '2026-09-01', '2026-09-30');
        $this->assertStringContainsString('Withholding tax deducted from payment', collect($september->rows)->pluck('label')->implode(' | '));
        $this->assertEqualsWithDelta(0, $september->closing, 0.001);

        $open = $this->statement($this->vendor, Statement::OPEN_ITEMS, null, '2026-10-20');
        $this->assertEqualsWithDelta(24000, $open->closing, 0.001);
        $this->assertEqualsWithDelta(32000, collect($open->open['items'])->sum('amount'), 0.001, 'B2 24,000 + asset 8,000');
        $this->assertEqualsWithDelta(8000, collect($open->open['credits'])->sum('amount'), 0.001, 'credit 2,000 + advance 6,000');

        $vendor = $this->vendor->fresh();
        $this->assertEqualsWithDelta(24000, (float) $vendor->outstanding_balance, 0.001, 'B2');
        $this->assertEqualsWithDelta(24000, $vendor->outstanding_balance - $vendor->openCreditBalance() - $vendor->advanceBalance() + 8000, 0.001);
        $this->get(route('vendors.show', $vendor))->assertOk()->assertSee('Balance we owe')->assertSee('24,000.00')
            ->assertSee(route('vendors.statement', $vendor));
        $this->get(route('vendors.statement', [$vendor, 'type' => 'open', 'to' => '2026-10-20']))->assertOk()->assertSee('Unpaid bills')->assertSee('Advance not yet used');
        $this->get(route('reports.supplier-statement', ['vendor_id' => $vendor->id]))->assertOk()->assertSee('24,000.00');
        $pdf = $this->get(route('vendors.statement.pdf', [$vendor, 'type' => 'open', 'to' => '2026-10-20']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    // ---- email ---------------------------------------------------------

    public function test_email_statement_is_queued_with_the_pdf_and_logged(): void
    {
        Notification::fake();
        $this->invoice(12500, '2026-10-01', '2026-10-31');

        $this->get(route('customers.statement', [$this->customer, 'type' => 'open', 'to' => '2026-10-20']))
            ->assertSee('Your statement from')->assertSee('₦12,500.00 owed to us');

        $this->post(route('customers.statement.email', $this->customer), [
            'type' => 'open', 'to' => '2026-10-20', 'subject' => 'Your October statement', 'message' => "Please see attached.\n\nThanks.",
        ])->assertSessionHas('success');

        Notification::assertSentTo($this->customer, StatementNotification::class, function (StatementNotification $n) {
            $mail = $n->toMail($this->customer);
            $this->assertSame('Your October statement', $mail->subject);
            $this->assertSame(['Please see attached.', 'Thanks.'], $mail->introLines);
            $this->assertCount(1, $mail->rawAttachments);
            $this->assertStringStartsWith('%PDF', $mail->rawAttachments[0]['data']);
            $this->assertSame('statement-aminu-stores-2026-10-20.pdf', $mail->rawAttachments[0]['name']);

            return $n->type === 'open' && $n->to === '2026-10-20' && in_array('mail', $n->via($this->customer), true);
        });
        $this->assertTrue(ActivityLog::where('model_type', Customer::class)->where('model_id', $this->customer->id)
            ->where('action', ActivityLog::ACTION_SENT)->where('description', 'like', '%aminu@example.com%')->exists());
    }

    public function test_statement_email_goes_on_the_queue(): void
    {
        Queue::fake();
        $this->post(route('vendors.statement.email', $this->vendor), [
            'type' => 'activity', 'from' => '2026-10-01', 'to' => '2026-10-20', 'subject' => 'Statement', 'message' => 'Attached.',
        ])->assertSessionHas('success');

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof StatementNotification);
    }

    public function test_email_needs_an_email_address(): void
    {
        Notification::fake();
        $this->customer->update(['email' => null]);

        $this->post(route('customers.statement.email', $this->customer), ['subject' => 'S', 'message' => 'M'])
            ->assertSessionHas('error');
        Notification::assertNothingSent();
    }

    public function test_bulk_send_to_ticked_customers_or_everyone_with_a_balance(): void
    {
        Notification::fake();
        $noEmail = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bello Ventures', 'email' => null]);
        $nothingOwed = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Chidi Ltd', 'email' => 'chidi@example.com']);
        $this->invoice(1000, '2026-10-01', '2026-10-31');
        $this->invoice(2000, '2026-10-01', '2026-10-31', 'unpaid', $noEmail);

        $form = ['type' => 'open', 'to' => '2026-10-20', 'subject' => 'Statement for {name}', 'message' => 'Balance: {balance} ({period}).'];

        $this->post(route('customers.statements.send'), $form + ['scope' => 'balance'])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Statements are being emailed to 1 customer. Skipped 1 with no email address: Bello Ventures.');
        Notification::assertSentTo($this->customer, StatementNotification::class, fn ($n) => $n->subject === 'Statement for Aminu Stores'
            && $n->body === 'Balance: ₦1,000.00 owed to us (as at 20 Oct 2026).');
        Notification::assertNotSentTo($nothingOwed, StatementNotification::class);

        $this->post(route('customers.statements.send'), $form + ['scope' => 'selected', 'ids' => [$this->customer->id, $noEmail->id, $nothingOwed->id]])
            ->assertSessionHas('success', 'Statements are being emailed to 2 customers. Skipped 1 with no email address: Bello Ventures.');
        Notification::assertSentTo($nothingOwed, StatementNotification::class);

        $this->post(route('customers.statements.send'), $form + ['scope' => 'selected'])->assertSessionHasErrors('ids');
    }

    public function test_bulk_send_to_suppliers(): void
    {
        Notification::fake();
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'items' => [['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 3000, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->post(route('vendors.statements.send'), ['scope' => 'balance', 'type' => 'activity', 'from' => '2026-10-01', 'to' => '2026-10-20',
            'subject' => 'Statement', 'message' => 'By our records the balance is {balance}.'])
            ->assertRedirect(route('vendors.index'))->assertSessionHas('success');
        Notification::assertSentTo($this->vendor, StatementNotification::class, fn ($n) => $n->body === 'By our records the balance is ₦3,000.00 owed to you.');
    }

    public function test_bulk_send_button_on_the_lists(): void
    {
        $this->get(route('customers.index'))->assertOk()->assertSee('Email statements')->assertSee('All customers with a balance');
        $this->get(route('vendors.index'))->assertOk()->assertSee('Email statements')->assertSee('All suppliers with a balance');
    }

    // ---- permissions, businesses, feature flag ---------------------------

    public function test_permissions(): void
    {
        $viewer = $this->createUserForTenant($this->tenant, ['view customers']);
        $this->actingAs($viewer);
        $this->get(route('customers.statement', $this->customer))->assertOk()->assertDontSee('Email statement');
        $this->post(route('customers.statement.email', $this->customer), ['subject' => 'S', 'message' => 'M'])->assertForbidden();
        $this->post(route('customers.statements.send'), ['scope' => 'balance', 'subject' => 'S', 'message' => 'M'])->assertForbidden();
        $this->get(route('vendors.statement', $this->vendor))->assertForbidden();

        $this->actingAs($this->createUserForTenant($this->tenant, ['view reports']));
        $this->get(route('vendors.statement', $this->vendor))->assertOk();
        $this->get(route('customers.statement.pdf', $this->customer))->assertOk();

        $this->actingAs($this->createUserForTenant($this->tenant, ['view invoices']));
        $this->get(route('customers.statement', $this->customer))->assertForbidden();
    }

    public function test_other_businesses_statements_are_out_of_reach(): void
    {
        Notification::fake();
        $other = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $theirs = Customer::withoutEvents(fn () => Customer::factory()->create(['tenant_id' => $other->id, 'email' => 'them@example.com']));
        $theirVendor = Vendor::withoutEvents(fn () => Vendor::factory()->create(['tenant_id' => $other->id, 'email' => 'v@example.com']));

        $this->get(route('customers.statement', $theirs))->assertNotFound();
        $this->get(route('customers.statement.pdf', $theirs))->assertNotFound();
        $this->get(route('vendors.statement', $theirVendor))->assertNotFound();
        $this->get(route('reports.customer-statement', ['customer_id' => $theirs->id]))->assertNotFound();
        $this->post(route('customers.statement.email', $theirs), ['subject' => 'S', 'message' => 'M'])->assertNotFound();

        $this->post(route('customers.statements.send'), ['scope' => 'selected', 'ids' => [$theirs->id], 'subject' => 'S', 'message' => 'M']);
        Notification::assertNotSentTo($theirs, StatementNotification::class);
    }

    public function test_feature_switch(): void
    {
        config(['mybooks.features.statements' => false]);

        $this->get(route('customers.statement', $this->customer))->assertNotFound();
        $this->post(route('customers.statements.send'), ['scope' => 'balance'])->assertNotFound();
        $this->get(route('customers.show', $this->customer))->assertOk()->assertDontSee(route('customers.statement', $this->customer));
        $this->get(route('customers.index'))->assertOk()->assertDontSee('Email statements');
        // The reports statement stays (with the corrected figures), without PDF or email.
        $this->get(route('reports.customer-statement', ['customer_id' => $this->customer->id]))->assertOk()->assertDontSee('Download PDF');
    }

    public function test_deleted_supplier_payment_comes_off_again(): void
    {
        $bill = app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'items' => [['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0]],
        ], $this->user->id);
        $payment = app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-10-02', 'amount' => 4000, 'payment_method' => 'cash',
        ], $this->user->id);
        $this->delete(route('payments-made.destroy', $payment))->assertSessionHasNoErrors();

        $this->assertTrue(PaymentMade::withTrashed()->findOrFail($payment->id)->trashed());
        $s = $this->statement($this->vendor, Statement::ACTIVITY, '2026-10-01', '2026-10-20');
        $this->assertEqualsWithDelta(10000, $s->closing, 0.001);
        $this->assertEqualsWithDelta(10000, (float) Bill::find($bill->id)->balance_due, 0.001);
    }
}
