<?php

namespace Tests\Feature\Features;

use App\Actions\Invoices\SaveInvoice;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Journal;
use App\Services\Accounting\VatReturnForm;
use App\Services\JournalService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The monthly VAT return in the NRS VAT Form 002 layout (part 2): a worked
 * September 2026 example in Naira mixing standard, zero-rated and exempt
 * sales and purchases with a credit note.
 */
class VatReturnFormTest extends TestCase
{
    use VatSeptemberScenario;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ledgerMovement(string $code, string $from, string $to, bool $credit): float
    {
        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->firstOrFail();
        $sum = fn ($col) => (float) $account->journalEntries()->whereHas('journal', fn ($q) => $q->whereBetween('journal_date', [$from, $to]))->sum($col);

        return round($credit ? $sum('credit') - $sum('debit') : $sum('debit') - $sum('credit'), 2);
    }

    public function test_worked_september_example_fills_every_box(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $L = $r->viewData('lines');

        $expected = [
            10 => 450000, 15 => 120000, 20 => 470000, 25 => 100000, 30 => 68000, 35 => -20000, 40 => 282000,
            45 => 21150,
            50 => 70000, 55 => 10000, 60 => 80000, 65 => 0, 70 => 80000, 75 => 5250,
            80 => 15900, 85 => 0, 90 => 0, 95 => 15900, 100 => 0, 105 => 0, 110 => 0, 115 => 0, 120 => 15900,
        ];
        foreach ($expected as $no => $amount) {
            $this->assertEqualsWithDelta($amount, $L[$no], 0.001, "line {$no}");
        }

        // The boxes add up as the form says.
        $this->assertEqualsWithDelta($L[20] - $L[25] - $L[30] + $L[35], $L[40], 0.001);
        $this->assertEqualsWithDelta($L[40] * 0.075, $L[45], 0.001, 'all VATable sales at 7.5%');
        $this->assertEqualsWithDelta($L[45] - $L[75], $L[80], 0.001);
        $this->assertEqualsWithDelta($L[50] + $L[55] + $L[65], $L[70], 0.001);

        // And agree with the ledger's VAT accounts for the month.
        $this->assertEqualsWithDelta($this->ledgerMovement('2400', '2026-09-01', '2026-09-30', true), $L[45], 0.001);
        $this->assertEqualsWithDelta($this->ledgerMovement('1410', '2026-09-01', '2026-09-30', false), $L[75], 0.001);
        $rec = $r->viewData('reconciliation');
        $this->assertEqualsWithDelta(21150, $rec['output']['documents'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['output']['unexplained'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['output']['difference'], 0.001);
        $this->assertEqualsWithDelta(5250, $rec['input']['documents'], 0.001);
        $this->assertTrue($r->viewData('unclassified')->isEmpty());

        $r->assertSee('VAT Form 002')->assertSee('Dangote Stores')->assertSee('12345678-0001')->assertSee('KAS-881');
    }

    public function test_schedules_list_each_line_with_party_tin_number_and_vat(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        $form = app(VatReturnForm::class)->build($this->tenant->id, '2026-09');

        $laptop = $form['salesSchedule']->firstWhere('description', 'Laptop');
        $this->assertSame('Dangote Stores', $laptop->party);
        $this->assertSame('12345678-0001', $laptop->tin);
        $this->assertSame('2026-09-15', $laptop->date->toDateString());
        $this->assertEqualsWithDelta(72000, $laptop->net, 0.001, 'after its share of the discount');
        $this->assertEqualsWithDelta(5400, $laptop->vat, 0.001);
        $this->assertSame('zero', $form['salesSchedule']->firstWhere('description', 'Medical kit')->treatment);

        // The cancelled invoice and its reversal are both listed and net to nothing.
        $this->assertEqualsWithDelta(0, $form['salesSchedule']->where('description', 'Cancelled job')->sum('net'), 0.001);
        $this->assertNull($form['salesSchedule']->firstWhere('description', 'October job'));

        $credit = $form['adjustmentsSchedule']->sole();
        $this->assertSame('Credit note', $credit->document);
        $this->assertEqualsWithDelta(-20000, $credit->net, 0.001);
        $this->assertEqualsWithDelta(-1500, $credit->vat, 0.001);
        $this->assertSame('standard', $credit->treatment, 'taken from the invoice line');

        $stock = $form['purchasesSchedule']->firstWhere('description', 'Stock');
        $this->assertSame('KAS-881', $stock->number, "the supplier's invoice number");
        $this->assertSame('87654321-0001', $stock->tin);
        $this->assertEqualsWithDelta(4500, $stock->vat, 0.001);
        $this->assertSame('exempt', $form['purchasesSchedule']->firstWhere('description', 'Office rent')->treatment);
        $this->assertSame('standard', $form['purchasesSchedule']->firstWhere('description', 'Internet')->treatment);
    }

    public function test_credit_note_reduces_output_vat_in_the_month_it_is_issued(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();
        $t = $this->tenant->id;

        // A credit note in October against the September invoice.
        Carbon::setTestNow('2026-10-20 09:00:00');
        $note = CreditNote::create([
            'tenant_id' => $t, 'customer_id' => Customer::first()->id, 'invoice_id' => Invoice::whereDate('invoice_date', '2026-09-05')->value('id'),
            'credit_note_number' => CreditNote::generateNumber($t), 'credit_note_date' => '2026-10-05', 'status' => 'draft',
        ]);
        CreditNoteItem::create(['credit_note_id' => $note->id, 'description' => 'Rice', 'quantity' => 2, 'unit_price' => 5000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 10000]);
        CreditNoteItem::create(['credit_note_id' => $note->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 7.5, 'tax_amount' => 300, 'total' => 4300]);
        $note->update(['subtotal' => 14000, 'tax_amount' => 300, 'total' => 14300, 'balance' => 14300]);
        $note->open();

        $sep = app(VatReturnForm::class)->build($t, '2026-09')['lines'];
        $oct = app(VatReturnForm::class)->build($t, '2026-10')['lines'];

        $this->assertEqualsWithDelta(21150, $sep[45], 0.001, 'September unchanged');
        // October: the 40,000 invoice (3,000 VAT) less the credit (300 VAT).
        $this->assertEqualsWithDelta(2700, $oct[45], 0.001);
        $this->assertEqualsWithDelta(-4000, $oct[35], 0.001, 'standard-rated part of the credit');
        $this->assertEqualsWithDelta(-10000, $oct[30], 0.001, 'zero-rated rice credited off line 30');
        $this->assertEqualsWithDelta(30000, $oct[20], 0.001, '40,000 sales less the 10,000 zero-rated credit');
        $this->assertEqualsWithDelta(36000, $oct[40], 0.001);
        $this->assertEqualsWithDelta($oct[40] * 0.075, $oct[45], 0.001);
    }

    public function test_cancelling_an_earlier_month_sale_is_an_adjustment_in_the_month_cancelled(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        Carbon::setTestNow('2026-10-03 09:00:00');
        Invoice::whereDate('invoice_date', '2026-09-12')->first()->update(['status' => 'cancelled']); // the exempt land lease

        $oct = app(VatReturnForm::class)->build($this->tenant->id, '2026-10');
        $this->assertEqualsWithDelta(-100000, $oct['lines'][25], 0.001);
        $this->assertSame('adjustment', $oct['adjustmentsSchedule']->firstWhere('description', 'Land lease')->category);
        $this->assertEqualsWithDelta(470000, app(VatReturnForm::class)->build($this->tenant->id, '2026-09')['lines'][20], 0.001);
    }

    public function test_vat_posted_by_journal_is_shown_in_the_reconciliation(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        $service = app(JournalService::class);
        $journal = Journal::create([
            'tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id),
            'journal_date' => '2026-09-30', 'reference' => 'ADJ-1', 'description' => 'Output VAT correction',
            'status' => 'posted', 'is_posted' => true, 'posted_at' => now(),
        ]);
        $service->createEntry($journal, '1000', 100, 0, 'Cash');
        $service->createEntry($journal, '2400', 0, 100, 'Output VAT');
        $service->assertBalanced($journal);

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $rec = $r->viewData('reconciliation')['output'];
        $this->assertEqualsWithDelta(21250, $r->viewData('lines')[45], 0.001);
        $this->assertEqualsWithDelta(21150, $rec['documents'], 0.001);
        $this->assertEqualsWithDelta(100, $rec['other'], 0.001);
        $this->assertEqualsWithDelta(0, $rec['unexplained'], 0.001);
        $r->assertSee('Output VAT correction')->assertSee('is not line 40 at 7.5%', false);
    }

    public function test_unclassified_zero_vat_sales_are_flagged_and_can_be_classified(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        Carbon::setTestNow('2026-09-28 10:00:00');
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-09', 'due_date' => '2026-09-30', 'status' => 'unpaid',
            'items' => [
                ['description' => 'Tuition', 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0],
                ['description' => 'Printing', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 7.5],
            ],
        ], $this->user->id);

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk()->assertSee('not classified');
        $this->assertCount(1, $r->viewData('unclassified'));
        $this->assertEqualsWithDelta(60000, $r->viewData('lines')[40], 0.001, 'counted as VATable until classified');

        $line = InvoiceItem::where('description', 'Tuition')->first();
        $vatLine = InvoiceItem::where('description', 'Printing')->first();
        $this->post(route('reports.vat-return.classify'), ['month' => '2026-09', 'lines' => [
            ['type' => 'invoice', 'id' => $line->id, 'treatment' => 'zero'],
            ['type' => 'invoice', 'id' => $vatLine->id, 'treatment' => 'exempt'], // charged VAT: refused
        ]])->assertRedirect(route('reports.vat-return', ['month' => '2026-09']));

        $this->assertSame('zero', $line->fresh()->vat_treatment);
        $this->assertSame('standard', $vatLine->fresh()->vat_treatment);
        $lines = app(VatReturnForm::class)->build($this->tenant->id, '2026-09')['lines'];
        $this->assertEqualsWithDelta(50000, $lines[30], 0.001);
        $this->assertEqualsWithDelta(10000, $lines[40], 0.001);
    }

    public function test_another_business_cannot_see_or_classify_the_lines(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        $this->september();
        $theirs = InvoiceItem::where('description', 'Consulting')->first();
        $theirs->forceFill(['tax_rate' => 0, 'vat_treatment' => null])->saveQuietly();

        $intruder = $this->createUserForTenant($other, ['view reports', 'file vat-returns']);
        $this->actingAs($intruder);

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $this->assertEqualsWithDelta(0, $r->viewData('lines')[45], 0.001);
        $this->assertTrue($r->viewData('salesSchedule')->isEmpty());
        $r->assertDontSee('Dangote Stores');

        $this->post(route('reports.vat-return.classify'), ['month' => '2026-09', 'lines' => [
            ['type' => 'invoice', 'id' => $theirs->id, 'treatment' => 'exempt'],
        ]]);
        $this->assertNull($theirs->fresh()->vat_treatment);
    }

    public function test_permissions(): void
    {
        $this->createAuthenticatedUser([]);
        $this->get(route('reports.vat-return'))->assertForbidden();

        $this->actingAs($this->createUserForTenant($this->tenant, ['view reports']));
        $this->get(route('reports.vat-return'))->assertOk();
        $this->post(route('reports.vat-return.classify'), ['month' => '2026-09', 'lines' => []])->assertForbidden();
    }
}
