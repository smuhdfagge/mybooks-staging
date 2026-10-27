<?php

namespace Tests\Feature\Exports;

use App\Models\Export;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things an export has to get right: offering only what it can
 * actually produce, and writing figures a spreadsheet can add up.
 */
class ExportFormatsAndNumbersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: float|int}> */
    private function profitLoss(): array
    {
        return app(ReportExportService::class)->profitLossData([
            'revenue' => 2500000,
            'costOfGoodsSold' => 1000000,
            'grossProfit' => 1500000,
            'operatingExpenses' => 250000,
            'payroll' => 400000,
            'totalExpenses' => 650000,
            'netProfit' => 850000,
        ])['rows'];
    }

    public function test_excel_is_offered_only_when_it_can_be_produced(): void
    {
        $formats = Export::getFormats();

        // phpoffice/phpspreadsheet is not a dependency of MyBooks, so the
        // Excel option used to hand back a CSV without saying so.
        if (Export::excelSupported()) {
            $this->assertArrayHasKey(Export::FORMAT_XLSX, $formats);
        } else {
            $this->assertArrayNotHasKey(Export::FORMAT_XLSX, $formats);
        }

        $this->assertArrayHasKey(Export::FORMAT_CSV, $formats);
        $this->assertArrayHasKey(Export::FORMAT_JSON, $formats);
        $this->assertArrayHasKey(Export::FORMAT_PDF, $formats);
    }

    public function test_report_figures_are_numbers_a_spreadsheet_can_total(): void
    {
        $amounts = array_column($this->profitLoss(), 1);

        // Was "2,500,000.00", which a spreadsheet reads as text — which is
        // why a column of them could not be totalled.
        $this->assertSame('2500000.00', $amounts[0]);
        $this->assertSame('1500000.00', $amounts[2]);

        foreach ($amounts as $amount) {
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $amount, "{$amount} is not a plain number.");
        }
    }

    public function test_the_brackets_that_meant_a_negative_are_written_as_one(): void
    {
        $amounts = array_column($this->profitLoss(), 1);

        // "(1,000,000.00)" meant minus a million.
        $this->assertSame('-1000000.00', $amounts[1]);
        $this->assertSame('-650000.00', $amounts[5]);

        // Revenue less the costs is the net profit the report states, so
        // the column adds up to the right answer now.
        $this->assertEqualsWithDelta(
            850000.0,
            (float) $amounts[0] + (float) $amounts[1] + (float) $amounts[3] + (float) $amounts[4],
            0.01
        );
    }

    public function test_the_labels_are_left_alone(): void
    {
        $labels = array_column($this->profitLoss(), 0);

        $this->assertSame('Revenue', $labels[0]);
        $this->assertSame('Less: Cost of Goods Sold', $labels[1]);
        $this->assertSame('Net Profit', $labels[6]);
    }
}
