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
use App\Actions\VendorCredits\RefundVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\WhtCategory;
use App\Services\ChartOfAccountService;
use App\Services\Statements\ControlReconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Session 10: receivables / payables control accounts against the customer
 * and supplier statement balances.
 */
class ControlReconciliationTest extends TestCase
{
    protected Customer $customer;

    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser([
            'view reports', 'view customers', 'view vendors', 'create invoices', 'view invoices', 'create payments-received',
            'create bills', 'view bills', 'create payments-made', 'create fixed-assets', 'view fixed-assets',
            'create journals', 'view journals', 'post journals', 'edit journals',
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aminu Stores']);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dangote Supplies']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function invoice(float $amount, string $date): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => $date, 'due_date' => '2026-11-30', 'status' => 'unpaid',
            'items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    private function bill(float $amount, string $date): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'items' => [['description' => 'Supplies', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    /** @param array<string, mixed> $data */
    private function receive(array $data)
    {
        return app(RecordPaymentReceived::class)->handle($this->tenant->id, $data + ['customer_id' => $this->customer->id, 'payment_method' => 'cash'], $this->user->id);
    }

    /** @param array<string, mixed> $data */
    private function payOut(array $data)
    {
        return app(RecordPaymentMade::class)->handle($this->tenant->id, $data + ['vendor_id' => $this->vendor->id, 'payment_method' => 'cash'], $this->user->id);
    }

    /** @return array{receivables: array<string, mixed>, payables: array<string, mixed>} */
    private function check(string $asOf = '2026-10-20'): array
    {
        return app(ControlReconciliation::class)->run($this->tenant->id, $asOf);
    }

    private function accountId(string $code): int
    {
        return (int) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('id');
    }

    /** A manual journal through the journal screens: saved as a draft, then posted. */
    private function manualJournal(string $debitCode, string $creditCode, float $amount, string $date = '2026-10-10'): Journal
    {
        $this->post(route('journals.store'), [
            'journal_date' => $date, 'description' => 'Year-end adjustment',
            'entries' => [
                ['account_id' => $this->accountId($debitCode), 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->accountId($creditCode), 'debit' => 0, 'credit' => $amount],
            ],
        ])->assertSessionHasNoErrors();
        $journal = Journal::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->post(route('journals.post', $journal))->assertSessionHasNoErrors();

        return $journal->fresh();
    }

    /** Every customer and supplier document type, no deposits or advances. */
    private function everyDocument(): void
    {
        $a = $this->invoice(10000, '2026-09-05');               // 10,750
        $b = $this->invoice(20000, '2026-09-20');               // 21,500
        $this->receive(['invoice_id' => $a->id, 'payment_date' => '2026-10-01', 'amount' => 10000, 'wht_amount' => 750]);
        $this->receive(['invoice_id' => null, 'payment_date' => '2026-10-02', 'amount' => 1000]);
        $note = app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'credit_note_date' => '2026-10-03', 'reason' => 'price_adjustment', 'status' => 'open',
            'items' => [['description' => 'Discount', 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0]],
        ], $this->user->id);
        app(ApplyCreditNote::class)->handle($note, $b, 1000, '2026-10-04');
        app(RefundCreditNote::class)->handle($note->fresh(), ['refund_date' => '2026-10-06', 'amount' => 400, 'payment_method' => 'cash'], $this->user->id);
        $c = $this->invoice(3000, '2026-10-07');
        $c->update(['status' => 'cancelled']);
        $p = $this->receive(['invoice_id' => $b->id, 'payment_date' => '2026-10-08', 'amount' => 500]);
        app(DeletePaymentReceived::class)->handle($p->fresh());

        $b1 = $this->bill(40000, '2026-09-10');                  // 43,000
        $this->payOut(['bill_id' => $b1->id, 'payment_date' => '2026-10-01', 'amount' => 41000, 'wht_amount' => 2000,
            'wht_category_id' => WhtCategory::where('tenant_id', $this->tenant->id)->value('id')]);
        $this->bill(5000, '2026-10-02');
        $credit = app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'credit_date' => '2026-10-05', 'status' => 'open', 'reason' => 'price_adjustment',
            'items' => [['description' => 'Price reduction', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);
        app(RefundVendorCredit::class)->handle($credit, ['refund_date' => '2026-10-06', 'amount' => 500, 'payment_method' => 'cash'], $this->user->id);
        $this->post(route('fixed-assets.store'), [
            'name' => 'Generator', 'purchase_date' => '2026-10-09', 'in_service_date' => '2026-10-09', 'purchase_cost' => 7000,
            'salvage_value' => 0, 'useful_life' => 5, 'depreciation_method' => 'straight_line', 'funding_source' => 'on_account', 'vendor_id' => $this->vendor->id,
        ])->assertSessionHasNoErrors();
    }

    // ---- tests ---------------------------------------------------------

    public function test_balanced_books_show_no_difference(): void
    {
        $this->everyDocument();

        foreach (['2026-10-20', '2026-10-07', '2026-09-30'] as $date) {
            $check = $this->check($date);
            foreach ($check as $side => $section) {
                $this->assertEqualsWithDelta(0, $section['difference'], 0.001, "{$side} at {$date}");
                $this->assertSame([], $section['items'], "{$side} at {$date}");
            }
        }

        $check = $this->check();
        // 10,750 + 21,500 - 10,750 - 1,000 - 2,000 + 400 = 18,900
        $this->assertEqualsWithDelta(18900, $check['receivables']['ledger'], 0.001);
        // 43,000 - 43,000 + 5,375 - 1,500 + 500 + 7,000 = 11,375
        $this->assertEqualsWithDelta(11375, $check['payables']['ledger'], 0.001);

        $this->get(route('reports.control-reconciliation'))->assertOk()
            ->assertSee('Receivables &amp; payables check', false)->assertSee('Agrees')->assertSee('18,900.00')->assertSee('11,375.00');
    }

    public function test_deposits_and_advances_explain_the_difference(): void
    {
        $invoice = $this->invoice(4000, '2026-10-01');                       // 4,300
        $deposit = $this->receive(['is_deposit' => true, 'payment_date' => '2026-09-25', 'amount' => 5000]);
        $this->receive(['invoice_id' => $invoice->id, 'payment_date' => '2026-10-02', 'amount' => 1000, 'apply_deposit_id' => $deposit->id, 'deposit_amount' => 1000]);
        $bill = $this->bill(10000, '2026-10-01');                             // 10,750
        $advance = $this->payOut(['is_advance' => true, 'payment_date' => '2026-09-28', 'amount' => 6000]);
        app(ApplySupplierAdvance::class)->handle($advance, $bill, 2000, '2026-10-03');

        $check = $this->check();

        // Customer owes 4,300 - 5,000 = -700 on the statement; receivables hold 3,300; deposits 4,000.
        $ar = $check['receivables'];
        $this->assertEqualsWithDelta(3300, $ar['ledger'], 0.001);
        $this->assertEqualsWithDelta(-700, $ar['statements'], 0.001);
        $this->assertEqualsWithDelta(4000, $ar['difference'], 0.001);
        $this->assertCount(1, $ar['items']);
        $this->assertSame('held', $ar['items'][0]['kind']);
        $this->assertTrue($ar['items'][0]['expected']);
        $this->assertEqualsWithDelta(4000, $ar['items'][0]['amount'], 0.001);
        $this->assertSame(0.0, $ar['unexplained']);

        $ap = $check['payables'];
        $this->assertEqualsWithDelta(8750, $ap['ledger'], 0.001);
        $this->assertEqualsWithDelta(4750, $ap['statements'], 0.001);
        $this->assertEqualsWithDelta(4000, $ap['difference'], 0.001, 'advance left, kept in Supplier Advances');
        $this->assertSame('held', $ap['items'][0]['kind']);

        $this->get(route('reports.control-reconciliation'))->assertOk()->assertSee('Difference explained')
            ->assertSee('Unused deposit from Aminu Stores')->assertSee('Unused advance to Dangote Supplies');
    }

    public function test_manual_journal_to_receivables_is_a_difference_and_listed(): void
    {
        $this->invoice(10000, '2026-10-01');
        $journal = $this->manualJournal('1200', '4200', 750);
        $this->manualJournal('6990', '2000', 300);

        $check = $this->check();
        $this->assertEqualsWithDelta(750, $check['receivables']['difference'], 0.001);
        $item = $check['receivables']['items'][0];
        $this->assertSame('journal', $item['kind']);
        $this->assertStringContainsString("Manual journal {$journal->journal_number} posted straight to Accounts Receivable", $item['label']);
        $this->assertSame(route('journals.show', $journal->id), $item['url']);
        $this->assertEqualsWithDelta(750, $item['amount'], 0.001);
        $this->assertSame(0.0, $check['receivables']['unexplained']);

        $this->assertEqualsWithDelta(300, $check['payables']['difference'], 0.001);
        $this->assertSame('journal', $check['payables']['items'][0]['kind']);

        // Before the journal's date there is no difference.
        $this->assertEqualsWithDelta(0, $this->check('2026-10-09')['receivables']['difference'], 0.001);

        $this->get(route('reports.control-reconciliation'))->assertOk()->assertSee('Needs a look')
            ->assertSee($journal->journal_number)->assertSee(route('journals.show', $journal->id));
    }

    public function test_document_whose_journal_was_changed_is_listed(): void
    {
        $invoice = $this->invoice(10000, '2026-10-01');                     // 10,750
        $journal = Journal::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->firstOrFail();
        // Someone changed the journal lines directly in the database (still balanced).
        $ar = JournalEntry::where('journal_id', $journal->id)->where('account_id', $this->accountId('1200'))->firstOrFail();
        DB::table('journal_entries')->where('id', $ar->id)->update(['debit' => 9750]);
        DB::table('journal_entries')->where('journal_id', $journal->id)->where('account_id', $this->accountId('4000'))->update(['credit' => 9000]);

        $bill = $this->bill(2000, '2026-10-02');
        DB::table('journal_entries')->whereIn('journal_id', Journal::where('reference_type', Bill::class)->where('reference_id', $bill->id)->pluck('id'))->delete();

        $check = $this->check();
        $this->assertEqualsWithDelta(-1000, $check['receivables']['difference'], 0.001);
        $item = $check['receivables']['items'][0];
        $this->assertSame('mismatch', $item['kind']);
        $this->assertStringContainsString("Invoice {$invoice->invoice_number}: journal doesn't match the document", $item['label']);
        $this->assertStringContainsString('9,750.00', $item['detail']);
        $this->assertStringContainsString('10,750.00', $item['detail']);
        $this->assertSame(route('invoices.show', $invoice->id), $item['url']);

        $this->assertEqualsWithDelta(-2150, $check['payables']['difference'], 0.001);
        $this->assertSame('no_journal', $check['payables']['items'][0]['kind']);
        $this->assertStringContainsString("Bill {$bill->bill_number}", $check['payables']['items'][0]['label']);
    }

    public function test_opening_balance_and_asset_without_supplier_are_listed(): void
    {
        ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1200')->update(['opening_balance' => 5000]);
        $this->post(route('fixed-assets.store'), [
            'name' => 'Laptop', 'purchase_date' => '2026-10-09', 'in_service_date' => '2026-10-09', 'purchase_cost' => 900,
            'salvage_value' => 0, 'useful_life' => 3, 'depreciation_method' => 'straight_line', 'funding_source' => 'on_account',
        ])->assertSessionHasNoErrors();

        $check = $this->check();
        $this->assertEqualsWithDelta(5000, $check['receivables']['difference'], 0.001);
        $this->assertSame('opening', $check['receivables']['items'][0]['kind']);
        $this->assertEqualsWithDelta(900, $check['payables']['difference'], 0.001);
        $this->assertSame('no_party', $check['payables']['items'][0]['kind']);
        $this->assertStringContainsString('Laptop', $check['payables']['items'][0]['label']);
    }

    public function test_other_businesses_are_left_out(): void
    {
        $this->invoice(1000, '2026-10-01');
        $other = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $mine = $this->user;
        $theirUser = $this->createUserForTenant($other);
        $this->actingAs($theirUser);
        app(ChartOfAccountService::class)->createDefaultAccounts($other->id);
        $theirCustomer = Customer::factory()->create(['tenant_id' => $other->id]);
        app(SaveInvoice::class)->create($other->id, [
            'customer_id' => $theirCustomer->id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31', 'status' => 'unpaid',
            'items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0]],
        ], $theirUser->id);
        $this->actingAs($mine);

        $check = $this->check();
        $this->assertEqualsWithDelta(1075, $check['receivables']['ledger'], 0.001);
        $this->assertEqualsWithDelta(1075, $check['receivables']['statements'], 0.001);
        $this->assertSame([], $check['receivables']['items']);
    }

    public function test_journal_form_warns_about_control_accounts(): void
    {
        $page = $this->get(route('journals.create'))->assertOk()->assertSee('control-account-warning');
        $this->assertStringContainsString('"'.$this->accountId('1200').'":"1200 - Accounts Receivable"', $page->getContent());
        $this->assertStringContainsString('"'.$this->accountId('2000').'":"2000 - Accounts Payable"', $page->getContent());

        config(['mybooks.features.statements' => false]);
        $this->get(route('journals.create'))->assertOk()->assertDontSee('control-account-warning');
    }

    public function test_permission_and_feature_switch(): void
    {
        $this->actingAs($this->createUserForTenant($this->tenant, ['view customers']));
        $this->get(route('reports.control-reconciliation'))->assertForbidden();

        $this->actingAs($this->user);
        config(['mybooks.features.statements' => false]);
        $this->get(route('reports.control-reconciliation'))->assertNotFound();
        $this->get(route('reports.index'))->assertOk()->assertDontSee('Receivables &amp; Payables Check', false);
    }
}
