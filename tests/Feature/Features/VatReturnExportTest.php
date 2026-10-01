<?php

namespace Tests\Feature\Features;

use App\Models\Customer;
use App\Services\Accounting\VatReturnForm;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * VAT return exports (part 3): the Form 002 PDF and the CSV schedules.
 */
class VatReturnExportTest extends TestCase
{
    use VatSeptemberScenario;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<list<string>> */
    private function csv(array $query): array
    {
        $response = $this->get(route('reports.vat-return.export', $query + ['month' => '2026-09', 'format' => 'csv']))->assertOk();
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

        return array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), array_values(array_filter(explode("\n", $body))));
    }

    public function test_pdf_is_laid_out_like_form_002(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->tenant->update(['tax_number' => '99887766-0001']);
        $this->september();

        $response = $this->get(route('reports.vat-return.export', ['month' => '2026-09', 'format' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('vat-return-2026-09.pdf', $response->headers->get('content-disposition'));

        // The HTML behind it carries the form's lines and the schedules.
        $html = view('reports.pdf.vat-return', app(VatReturnForm::class)->build($this->tenant->id, '2026-09') + ['tenant' => $this->tenant->fresh()])->render();
        foreach (['VALUE ADDED TAX RETURN - FORM 002', '99887766-0001', 'Period beginning', '01/09/2026', '30/09/2026',
            'Sales subject to VAT (line 20 - 25 - 30 + 35)', '282,000.00', '21,150.00', '(20,000.00)', '15,900.00',
            'Declaration', 'Schedule of sales', 'Schedule of purchases', 'Dangote Stores', '12345678-0001', '21/10/2026'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
    }

    public function test_taxpromax_sales_upload_has_the_template_columns(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();
        // A walk-in cash sale has no TIN: the template wants 0.
        $rows = $this->csv(['schedule' => 'sales-upload']);

        $this->assertSame(['Customer Name', 'Customer TIN', 'Item/Service Sold', 'Cost/Price', 'Description', 'VAT Status'], $rows[0]);
        $byItem = collect(array_slice($rows, 1))->keyBy(2);

        $this->assertSame(['Dangote Stores', '12345678-0001', 'Consulting', '200000.00'], array_slice($byItem['Consulting'], 0, 4));
        $this->assertSame('0', $byItem['Consulting'][5], 'VATable');
        $this->assertSame('1', $byItem['Rice'][5], 'zero-rated');
        $this->assertSame('2', $byItem['Land lease'][5], 'exempt');
        $this->assertSame('72000.00', $byItem['Laptop'][3], 'after the discount');
        $this->assertSame('0', $byItem['Water'][1], 'no TIN known');
        $this->assertStringContainsString('Invoice', $byItem['Laptop'][4]);
        $this->assertArrayNotHasKey('Cancelled job', $byItem->all(), 'cancelled in the month: nothing to upload');
        $this->assertCount(6, $byItem);
        $this->assertEqualsWithDelta(470000, $byItem->sum(fn ($r) => (float) $r[3]), 0.001, 'line 20');
    }

    public function test_detailed_schedules_and_form_lines(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        $purchases = $this->csv(['schedule' => 'purchases']);
        $this->assertSame(['Supplier', 'Supplier TIN', 'Invoice number', 'Date', 'Description', 'VAT status', 'Amount excl. VAT (NGN)', 'VAT (NGN)', 'Document'], $purchases[0]);
        $stock = collect($purchases)->firstWhere(4, 'Stock');
        $this->assertSame(['Kano Agro Supplies', '87654321-0001', 'KAS-881', '08/09/2026', 'Stock', 'Standard-rated', '60000.00', '4500.00', 'Bill'], $stock);

        $adjustments = $this->csv(['schedule' => 'adjustments']);
        $this->assertSame(['-20000.00', '-1500.00'], array_slice($adjustments[1], 6, 2), 'negative numbers stay numbers');

        $form = collect($this->csv(['schedule' => 'form']))->keyBy(1);
        $this->assertSame('282000.00', $form['40'][3]);
        $this->assertSame('21150.00', $form['45'][3]);
        $this->assertSame('15900.00', $form['120'][3]);
    }

    public function test_formula_like_names_are_neutralised(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->september();
        Customer::first()->update(['name' => '=HYPERLINK("x")']);

        $rows = $this->csv(['schedule' => 'sales-upload']);
        $this->assertStringStartsWith("'=", $rows[1][0]);
    }

    public function test_exports_are_for_the_signed_in_business_only(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser(['view reports']);
        $this->september();

        $this->actingAs($this->createUserForTenant($other, ['view reports']));
        $rows = $this->csv(['schedule' => 'sales']);
        $this->assertCount(1, $rows, 'headers only');
        $this->assertStringNotContainsString('Dangote', implode(',', $this->csv(['schedule' => 'sales-upload'])[0]));

        $this->actingAs($this->createUserForTenant($other, []));
        $this->get(route('reports.vat-return.export', ['month' => '2026-09', 'format' => 'pdf']))->assertForbidden();
    }
}
