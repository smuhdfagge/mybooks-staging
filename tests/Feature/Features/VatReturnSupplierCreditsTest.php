<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\Payments\RecordPaymentReceived;
use App\Actions\VendorCredits\RefundVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Actions\VendorCredits\VoidVendorCredit;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\Item;
use App\Models\PaymentReceived;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Models\VendorCredit;
use App\Models\VendorCreditItem;
use App\Models\WhtCategory;
use App\Services\Accounting\VatReturnForm;
use App\Services\Accounting\VatTreatment;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The VAT return with everything on main that touches the VAT accounts:
 * an October 2026 month with invoices, an invoice refund, a credit note,
 * a bill, supplier credits (one refunded, one voided), an expense, and
 * payments with WHT and a supplier advance (which post no VAT).
 */
class VatReturnSupplierCreditsTest extends TestCase
{
    protected Vendor $vendor;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpBusiness(): void
    {
        $this->createAuthenticatedUser(['view reports', 'edit invoices', 'view invoices', 'create bills', 'view bills']);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kano Office Supplies', 'tax_number' => '11112222-0001']);
    }

    private function rate(string $code): TaxRate
    {
        return TaxRate::where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function serviceItem(string $name, string $rateCode): Item
    {
        return Item::factory()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'type' => 'service', 'track_inventory' => false,
            'tax_rate_id' => $this->rate($rateCode)->id,
        ]);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function supplierCredit(array $lines, ?Bill $bill, string $date): VendorCredit
    {
        return app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill?->id, 'credit_date' => $date,
            'status' => 'open', 'reason' => 'goods_returned', 'items' => $lines,
        ], $this->user->id);
    }

    private function bill(Item $seed, string $date = '2026-10-05'): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30', 'reference' => 'KOS-501', 'items' => [
                ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 7.5],
                ['item_id' => $seed->id, 'description' => 'Maize seed', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0],
                ['description' => 'Office rent', 'quantity' => 1, 'unit_price' => 30000, 'tax_rate' => 0, 'vat_treatment' => 'exempt'],
            ],
        ], $this->user->id);
    }

    private function ledgerMovement(string $code, bool $credit): float
    {
        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->firstOrFail();
        $sum = fn ($col) => (float) $account->journalEntries()->whereHas('journal', fn ($q) => $q->whereBetween('journal_date', ['2026-10-01', '2026-10-31']))->sum($col);

        return round($credit ? $sum('credit') - $sum('debit') : $sum('debit') - $sum('credit'), 2);
    }

    private function whtCategory(string $code): WhtCategory
    {
        return WhtCategory::where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    /**
     * Sales                                       net       VAT
     *   INV-A 10 Oct consulting 7.5%           100,000     7,500
     *                rice (zero-rated item)      20,000         0
     *   INV-B  3 Oct repairs 7.5%, paid          40,000     3,000
     *   Refund 18 Oct half of INV-B             -20,000    -1,500
     *   CN    15 Oct on INV-A consulting        -10,000      -750
     * Purchases
     *   BILL   5 Oct stationery 7.5%             50,000     3,750
     *                maize seed (zero item)      10,000         0
     *                office rent (exempt)        30,000         0
     *   SC1   12 Oct supplier credit on BILL:
     *                stationery                 -10,000      -750
     *                maize seed                  -2,000         0
     *                office rent                 -5,000         0
     *   SC2   22 Oct 4,000 + 300, voided 26 Oct (nets out)
     *   EXP   20 Oct internet                     8,000       600
     * No VAT: SC1 refund of 5,000, a WHT payment on the bill, a WHT
     * receipt on INV-A and a supplier advance.
     */
    private function october(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $t = $this->tenant->id;
        $u = $this->user->id;
        $customer = Customer::factory()->create(['tenant_id' => $t, 'name' => 'Sabon Gari Traders', 'tax_number' => '33334444-0001']);
        $rice = $this->serviceItem('Rice', 'VAT-ZERO');
        $seed = $this->serviceItem('Maize seed', 'VAT-ZERO');

        $invoices = app(SaveInvoice::class);
        $invA = $invoices->create($t, ['customer_id' => $customer->id, 'invoice_date' => '2026-10-10', 'due_date' => '2026-11-10', 'status' => 'unpaid', 'items' => [
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 7.5],
            ['item_id' => $rice->id, 'description' => 'Rice', 'quantity' => 4, 'unit_price' => 5000, 'tax_rate' => 0],
        ]], $u);
        $invB = $invoices->create($t, ['customer_id' => $customer->id, 'invoice_date' => '2026-10-03', 'due_date' => '2026-10-03', 'status' => 'unpaid', 'items' => [
            ['description' => 'Repairs', 'quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 7.5],
        ]], $u);
        PaymentReceived::create([
            'tenant_id' => $t, 'customer_id' => $customer->id, 'invoice_id' => $invB->id,
            'payment_number' => 'PAY-B', 'payment_date' => '2026-10-04', 'amount' => 43000, 'payment_method' => 'cash',
        ]);
        $this->post(route('invoices.refunds.store', $invB), [
            'amount' => 21500, 'refund_date' => '2026-10-18', 'refund_method' => array_key_first(InvoiceRefund::METHODS),
        ])->assertSessionHas('success');

        $note = CreditNote::create([
            'tenant_id' => $t, 'customer_id' => $customer->id, 'invoice_id' => $invA->id,
            'credit_note_number' => CreditNote::generateNumber($t), 'credit_note_date' => '2026-10-15', 'status' => 'draft',
        ]);
        CreditNoteItem::create(['credit_note_id' => $note->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5, 'tax_amount' => 750, 'total' => 10750]);
        $note->update(['subtotal' => 10000, 'tax_amount' => 750, 'total' => 10750, 'balance' => 10750]);
        $note->open();

        // WHT on a customer's payment: an income tax credit, not VAT.
        app(RecordPaymentReceived::class)->handle($t, [
            'customer_id' => $customer->id, 'invoice_id' => $invA->id, 'payment_date' => '2026-10-24',
            'amount' => 50000, 'payment_method' => 'cash', 'wht_category_id' => $this->whtCategory('supply_goods')->id,
        ], $u);

        $bill = $this->bill($seed);
        $sc1 = $this->supplierCredit([
            ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5],
            ['item_id' => $seed->id, 'description' => 'Maize seed', 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0],
            ['description' => 'Office rent', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
        ], $bill, '2026-10-12');
        app(RefundVendorCredit::class)->handle($sc1, ['refund_date' => '2026-10-20', 'amount' => 5000, 'payment_method' => 'cash'], $u);

        $sc2 = $this->supplierCredit([['description' => 'Toner', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 7.5]], null, '2026-10-22');
        app(VoidVendorCredit::class)->handle($sc2);

        app(RecordPaymentMade::class)->handle($t, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-10-22',
            'amount' => 20000, 'payment_method' => 'cash', 'wht_category_id' => $this->whtCategory('supply_goods')->id,
        ], $u);
        app(RecordPaymentMade::class)->handle($t, [
            'vendor_id' => $this->vendor->id, 'is_advance' => true, 'payment_date' => '2026-10-23',
            'amount' => 15000, 'payment_method' => 'cash',
        ], $u);

        $expense = Expense::withoutEvents(fn () => Expense::factory()->create([
            'tenant_id' => $t, 'name' => 'Internet', 'expense_date' => '2026-10-20', 'amount' => 8000, 'tax_amount' => 600, 'total' => 8600,
            'status' => Expense::STATUS_PAID,
            'expense_account_id' => ChartOfAccount::where('tenant_id', $t)->where('type', 'expense')->value('id'),
        ]));
        app(JournalService::class)->createExpenseJournal($expense);
    }

    public function test_a_month_with_every_vat_document_fills_form_002_and_agrees_with_the_ledger(): void
    {
        $this->setUpBusiness();
        $this->october();

        $r = $this->get(route('reports.vat-return', ['month' => '2026-10']))->assertOk();
        $L = $r->viewData('lines');

        $expected = [
            10 => 130000, 15 => 81000, 20 => 160000, 25 => 0, 30 => 20000, 35 => -30000, 40 => 110000,
            45 => 8250,
            50 => 48000, 55 => 8000, 60 => 56000, 65 => 0, 70 => 56000, 75 => 3600,
            80 => 4650, 85 => 0, 90 => 0, 95 => 4650, 100 => 0, 105 => 0, 110 => 0, 115 => 0, 120 => 4650,
        ];
        foreach ($expected as $no => $amount) {
            $this->assertEqualsWithDelta($amount, $L[$no], 0.001, "line {$no}");
        }
        $this->assertEqualsWithDelta($L[40] * 0.075, $L[45], 0.001, 'VATable sales at 7.5%');
        $this->assertEqualsWithDelta($L[50] * 0.075, $L[75], 0.001, 'standard-rated purchases at 7.5%');

        // Output and input VAT are the ledger's movements, all explained by documents.
        $this->assertEqualsWithDelta($this->ledgerMovement('2400', true), $L[45], 0.001);
        $this->assertEqualsWithDelta($this->ledgerMovement('1410', false), $L[75], 0.001);
        $rec = $r->viewData('reconciliation');
        foreach (['output' => 8250, 'input' => 3600] as $side => $vat) {
            $this->assertEqualsWithDelta($vat, $rec[$side]['documents'], 0.001, "{$side} from documents");
            $this->assertEqualsWithDelta(0, $rec[$side]['other'], 0.001, "{$side} posted without a document");
            $this->assertEqualsWithDelta(0, $rec[$side]['unexplained'], 0.001, "{$side} unexplained");
            $this->assertEqualsWithDelta(0, $rec[$side]['difference'], 0.001, "{$side} difference");
        }
        $this->assertTrue($r->viewData('unclassified')->isEmpty());

        // The supplier credit is on the purchases schedule as negative lines.
        $credits = $r->viewData('purchasesSchedule')->where('document', 'Supplier credit')->where('reversal', false);
        $byLine = $credits->groupBy('description')->map(fn ($g) => [$g->sum('net'), $g->sum('vat'), $g->first()->treatment]);
        $this->assertEqualsWithDelta(-10000, $byLine['Stationery'][0], 0.001);
        $this->assertEqualsWithDelta(-750, $byLine['Stationery'][1], 0.001);
        $this->assertSame([VatTreatment::STANDARD, VatTreatment::ZERO, VatTreatment::EXEMPT],
            [$byLine['Stationery'][2], $byLine['Maize seed'][2], $byLine['Office rent'][2]]);
        $r->assertSee('Supplier credit')->assertSee('Kano Office Supplies');

        // The PDF and the purchases CSV carry the same figures.
        $csv = $this->get(route('reports.vat-return.export', ['month' => '2026-10', 'format' => 'csv', 'schedule' => 'purchases']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Supplier credit', $csv);
        $this->assertStringContainsString('-10000.00', $csv);
        $this->get(route('reports.vat-return.export', ['month' => '2026-10', 'format' => 'pdf']))->assertOk();
    }

    public function test_supplier_credit_lines_record_their_vat_treatment(): void
    {
        $this->setUpBusiness();
        $seed = $this->serviceItem('Maize seed', 'VAT-ZERO');
        $exemptItem = $this->serviceItem('Hall hire', 'VAT-EXEMPT');
        $bill = $this->bill($seed);

        $credit = $this->supplierCredit([
            ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5],
            ['item_id' => $seed->id, 'description' => 'Seed', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ['description' => 'Office rent', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ['item_id' => $exemptItem->id, 'description' => 'Hall', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ['description' => 'Deposit returned', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'vat_treatment' => 'out_of_scope'],
            ['description' => 'Something else', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
        ], $bill, '2026-10-12');

        $this->assertSame(
            ['standard', 'zero', 'exempt', 'exempt', 'out_of_scope', null],
            $credit->items->sortBy('id')->pluck('vat_treatment')->all(),
        );

        // VAT charged is always standard, whatever was chosen.
        $line = VendorCreditItem::create(['vendor_credit_id' => $credit->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 7.5, 'tax_amount' => 7.5, 'total' => 107.5, 'vat_treatment' => 'exempt']);
        $this->assertSame('standard', $line->vat_treatment);

        // The web form rejects a made-up treatment.
        $this->post(route('vendor-credits.store'), [
            'vendor_id' => $this->vendor->id, 'credit_date' => '2026-10-12', 'status' => 'draft',
            'items' => [['description' => 'Y', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0, 'vat_treatment' => 'nonsense']],
        ])->assertSessionHasErrors('items.0.vat_treatment');
    }

    public function test_the_migration_fills_in_existing_supplier_credit_lines(): void
    {
        $this->setUpBusiness();
        $seed = $this->serviceItem('Maize seed', 'VAT-ZERO');
        $bill = $this->bill($seed);
        $credit = $this->supplierCredit([
            ['description' => 'Stationery', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5],
            ['item_id' => $seed->id, 'description' => 'Seed', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ['description' => 'Office rent', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ['description' => 'Something else', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
        ], $bill, '2026-10-12');
        DB::table('vendor_credit_items')->update(['vat_treatment' => null]);

        $migration = require database_path('migrations/2026_10_07_000001_add_vat_treatment_to_vendor_credit_items.php');
        $migration->up();
        $migration->up(); // runs again safely

        $this->assertSame(['standard', 'zero', 'exempt', null],
            DB::table('vendor_credit_items')->where('vendor_credit_id', $credit->id)->orderBy('id')->pluck('vat_treatment')->all());
    }

    public function test_voiding_an_earlier_months_supplier_credit_adds_it_back_in_the_month_voided(): void
    {
        $this->setUpBusiness();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $credit = $this->supplierCredit([['description' => 'Toner', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 7.5]], null, '2026-09-15');
        Carbon::setTestNow('2026-10-08 10:00:00');
        app(VoidVendorCredit::class)->handle($credit);

        $form = app(VatReturnForm::class);
        $sep = $form->build($this->tenant->id, '2026-09');
        $oct = $form->build($this->tenant->id, '2026-10');

        $this->assertEqualsWithDelta(-4000, $sep['lines'][50], 0.001);
        $this->assertEqualsWithDelta(-300, $sep['lines'][75], 0.001);
        $this->assertEqualsWithDelta(300, $sep['lines'][80], 0.001, 'a credit alone leaves VAT to pay');
        $this->assertEqualsWithDelta(4000, $oct['lines'][50], 0.001);
        $this->assertEqualsWithDelta(300, $oct['lines'][75], 0.001);
        foreach ([$sep, $oct] as $month) {
            $this->assertEqualsWithDelta(0, $month['reconciliation']['input']['unexplained'], 0.001);
            $this->assertEqualsWithDelta(0, $month['reconciliation']['input']['other'], 0.001);
        }
    }

    public function test_another_business_supplier_credits_stay_off_the_return(): void
    {
        $this->setUpBusiness();
        $mine = $this->tenant;
        $this->supplierCredit([['description' => 'Toner', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 7.5]], null, '2026-10-15');

        $this->app['auth']->forgetGuards();
        $this->createAuthenticatedUser(['view reports']);
        $this->assertNotSame($mine->id, $this->tenant->id);
        $r = $this->get(route('reports.vat-return', ['month' => '2026-10']))->assertOk();
        $this->assertEqualsWithDelta(0, $r->viewData('lines')[75], 0.001);
        $this->assertTrue($r->viewData('purchasesSchedule')->isEmpty());
        $r->assertDontSee('Kano Office Supplies');
    }
}
