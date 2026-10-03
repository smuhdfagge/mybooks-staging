<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\VatReturns\FileVatReturn;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\VatReturnFiling;
use App\Models\Vendor;
use App\Services\Accounting\VatReturn;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Marking a monthly VAT return as filed: hand-entered lines 65, 85 and 90,
 * the settlement into VAT Payable, and the credit carried to next month.
 */
class VatReturnFilingTest extends TestCase
{
    use VatSeptemberScenario;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    private function invoice(string $date, float $net, float $rate = 7.5): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_date' => $date, 'due_date' => $date, 'status' => 'unpaid',
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => $net, 'tax_rate' => $rate]],
        ], $this->user->id);
    }

    private function bill(string $date, float $net): void
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => $date, 'due_date' => $date,
            'items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => $net, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    public function test_filing_a_month_keeps_the_hand_entered_lines_and_settles_its_vat(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        $this->september();
        Carbon::setTestNow('2026-10-10 09:00:00');

        // Preview: the figures typed in show on the form before filing.
        $r = $this->get(route('reports.vat-return', ['month' => '2026-09', 'vat_withheld' => '1000', 'imports' => '5000']))->assertOk();
        $this->assertEqualsWithDelta(1000, $r->viewData('lines')[85], 0.001);
        $this->assertEqualsWithDelta(5000, $r->viewData('lines')[65], 0.001);
        $this->assertEqualsWithDelta(14900, $r->viewData('lines')[120], 0.001);
        $r->assertSee('File this return')->assertSee('21 October 2026')->assertSee('Mark as filed and settle VAT');

        $this->post(route('reports.vat-return.file'), [
            'month' => '2026-09', 'vat_withheld' => 1000, 'imports' => 5000, 'reference' => 'NRS-ACK-778',
        ])->assertRedirect(route('reports.vat-return', ['month' => '2026-09']))
            ->assertSessionHas('success', 'VAT return for September 2026 filed. Pay ₦14,900.00 to NRS by 21 October 2026.');

        $filing = VatReturnFiling::sole();
        $this->assertSame('2026-09', $filing->month);
        $this->assertSame('NRS-ACK-778', $filing->reference);
        $this->assertSame($this->user->id, $filing->filed_by);
        $this->assertEqualsWithDelta(21150, (float) $filing->output_vat, 0.001);
        $this->assertEqualsWithDelta(5250, (float) $filing->input_vat, 0.001);
        $this->assertEqualsWithDelta(14900, (float) $filing->vat_payable, 0.001);
        $this->assertEqualsWithDelta(14900, $filing->lines[120], 0.001);

        // Settled: September's output and input VAT moved to VAT Payable.
        $journal = $filing->settlementJournal;
        $this->assertSame(VatReturn::SETTLEMENT, $journal->journal_type);
        $this->assertSame('2026-09-30', $journal->journal_date->toDateString());
        $this->assertEqualsWithDelta(15900, $this->balance('2410'), 0.001);

        // The page shows the filed figures, without the filing form; the
        // hand-entered lines come from the filing, not the query string.
        $r = $this->get(route('reports.vat-return', ['month' => '2026-09', 'vat_withheld' => '0']))->assertOk();
        $this->assertEqualsWithDelta(1000, $r->viewData('lines')[85], 0.001);
        $r->assertSee('Marked as filed on 10/10/2026')->assertSee('NRS-ACK-778')->assertSee($journal->journal_number)
            ->assertDontSee('Mark as filed and settle VAT');
        $this->assertFalse($r->viewData('changedSinceFiling'));
        $this->get(route('reports.vat-return.export', ['month' => '2026-09', 'format' => 'pdf']))->assertOk();

        // Not twice.
        $this->post(route('reports.vat-return.file'), ['month' => '2026-09'])->assertSessionHasErrors('month');
        $this->assertSame(1, VatReturnFiling::count());
    }

    public function test_a_credit_is_carried_forward_and_relieved_the_next_month(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        Carbon::setTestNow('2026-08-15 09:00:00');
        $this->bill('2026-08-10', 10000); // input VAT 750, no sales
        Carbon::setTestNow('2026-09-15 09:00:00');
        $this->invoice('2026-09-05', 20000); // output VAT 1,500
        Carbon::setTestNow('2026-10-05 09:00:00');

        $file = app(FileVatReturn::class);
        $august = $file->handle($this->tenant->id, '2026-08', [], $this->user->id);
        $this->assertEqualsWithDelta(-750, $august->lines[95], 0.001);
        $this->assertEqualsWithDelta(750, (float) $august->credit_carried_forward, 0.001);
        $this->assertEqualsWithDelta(0, (float) $august->vat_payable, 0.001);

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $L = $r->viewData('lines');
        $this->assertEqualsWithDelta(750, $L[100], 0.001);
        $this->assertEqualsWithDelta(750, $L[110], 0.001);
        $this->assertEqualsWithDelta(0, $L[115], 0.001);
        $this->assertEqualsWithDelta(750, $L[120], 0.001);

        $september = $file->handle($this->tenant->id, '2026-09', [], $this->user->id);
        $this->assertEqualsWithDelta(750, (float) $september->credit_brought_forward, 0.001);
        $this->assertEqualsWithDelta(750, (float) $september->vat_payable, 0.001);
        // Both settled: VAT Payable holds September's 1,500 less August's 750 refund due.
        $this->assertEqualsWithDelta(750, $this->balance('2410'), 0.001);
    }

    public function test_a_month_already_settled_from_the_vat_report_or_with_no_vat_files_without_a_second_journal(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        Carbon::setTestNow('2026-09-15 09:00:00');
        $this->invoice('2026-09-05', 20000);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $settled = app(VatReturn::class)->settle($this->tenant->id, '2026-09-01', '2026-09-30');

        $file = app(FileVatReturn::class);
        $filing = $file->handle($this->tenant->id, '2026-09', [], $this->user->id);
        $this->assertSame($settled->id, $filing->settlement_journal_id);
        $this->assertSame(1, Journal::where('journal_type', VatReturn::SETTLEMENT)->count());

        // A nil return: nothing to settle, still filed.
        $nil = $file->handle($this->tenant->id, '2026-07', [], $this->user->id);
        $this->assertNull($nil->settlement_journal_id);
        $this->assertEqualsWithDelta(0, (float) $nil->vat_payable, 0.001);
    }

    public function test_a_month_can_only_be_filed_once_it_has_ended(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        Carbon::setTestNow('2026-10-20 09:00:00');

        $this->expectException(ValidationException::class);
        app(FileVatReturn::class)->handle($this->tenant->id, '2026-10', [], $this->user->id);
    }

    public function test_a_document_changed_after_filing_is_flagged(): void
    {
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        Carbon::setTestNow('2026-09-15 09:00:00');
        $this->invoice('2026-09-05', 20000);
        Carbon::setTestNow('2026-10-05 09:00:00');
        app(FileVatReturn::class)->handle($this->tenant->id, '2026-09', [], $this->user->id);

        $this->invoice('2026-09-28', 10000); // backdated after filing
        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $this->assertTrue($r->viewData('changedSinceFiling'));
        $r->assertSee('changed since it was filed');
    }

    public function test_filing_needs_the_permission_and_stays_within_the_business(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk()->assertDontSee('Mark as filed and settle VAT');
        $this->post(route('reports.vat-return.file'), ['month' => '2026-09'])->assertForbidden();
        $this->assertSame(0, VatReturnFiling::count());

        // Another business's filing and credit don't show here.
        $this->app['auth']->forgetGuards();
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        $other = $this->tenant;
        Carbon::setTestNow('2026-08-15 09:00:00');
        $this->bill('2026-08-10', 10000);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->post(route('reports.vat-return.file'), ['month' => '2026-08'])->assertSessionHasNoErrors();
        $this->assertSame($other->id, VatReturnFiling::sole()->tenant_id);

        $this->app['auth']->forgetGuards();
        $this->createAuthenticatedUser(['view reports', 'file vat-returns']);
        $r = $this->get(route('reports.vat-return', ['month' => '2026-08']))->assertOk();
        $this->assertNull($r->viewData('filing'));
        $this->assertEqualsWithDelta(0, $this->get(route('reports.vat-return', ['month' => '2026-09']))->viewData('lines')[100], 0.001);
        $this->post(route('reports.vat-return.file'), ['month' => '2026-08'])->assertSessionHasNoErrors();
        $this->assertSame(2, VatReturnFiling::withoutGlobalScopes()->count());
    }

    public function test_draft_documents_in_the_month_are_pointed_out(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        Carbon::setTestNow('2026-10-05 09:00:00');
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        foreach (['2026-09-01', '2026-09-30', '2026-10-01'] as $date) {
            app(SaveInvoice::class)->create($this->tenant->id, [
                'customer_id' => $customer->id, 'invoice_date' => $date, 'due_date' => $date, 'status' => 'draft',
                'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5]],
            ], $this->user->id);
        }

        $r = $this->get(route('reports.vat-return', ['month' => '2026-09']))->assertOk();
        $this->assertSame(['invoice' => 2], $r->viewData('drafts'));
        $r->assertSee('Not on this return: 2 draft invoices');
        $this->assertEqualsWithDelta(0, $r->viewData('lines')[45], 0.001);
    }
}
