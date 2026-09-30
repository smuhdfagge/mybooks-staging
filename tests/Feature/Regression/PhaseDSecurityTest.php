<?php

namespace Tests\Feature\Regression;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Journal;
use App\Services\BankFileExporters\CsvBankExporter;
use App\Services\ExportService;
use App\Services\ReportExportService;
use App\Support\Csv;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Round 3, Phase D: security fixes S2 to S8.
 */
class PhaseDSecurityTest extends TestCase
{
    // ── S3: journal account options ─────────────────────────────

    public function test_s3_journal_pages_add_account_options_as_text_not_html(): void
    {
        $this->createAuthenticatedUser(['create journals', 'edit journals']);
        ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '<img src=x onerror=alert(1)>',
            'is_active' => true,
        ]);
        $journal = Journal::withoutEvents(fn () => Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'is_posted' => false,
        ]));

        foreach ([route('journals.create'), route('journals.edit', $journal)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // The "add row" script used to paste account names into an HTML
            // string, so a name with markup ran as code.
            $this->assertStringNotContainsString('${a.name}</option>', $html);
            $this->assertStringNotContainsString('${accountOptions}', $html);
            $this->assertStringContainsString('select.add(new Option(', $html);
        }
    }

    // ── S4: CSV formula cells ───────────────────────────────────

    public function test_s4_data_export_csv_turns_formula_names_into_text(): void
    {
        $this->createAuthenticatedUser();
        Storage::fake('exports');
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '=HYPERLINK("http://evil.test","Click")',
        ]);

        $export = Export::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_PENDING,
        ]);
        app(ExportService::class)->processExport($export);

        $csv = Storage::disk('exports')->get($export->fresh()->file_path);
        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_s4_report_and_bank_csv_escape_formulas_but_keep_numbers(): void
    {
        $response = (new ReportExportService)->exportToCsv(
            [['@SUM(A1:A9)', '-1,500.00', '+2348012345678x', 250]],
            ['Name', 'Amount', 'Phone', 'Qty'],
        );
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("'@SUM(A1:A9)", $csv);
        $this->assertStringContainsString(',"-1,500.00",', $csv);
        $this->assertStringContainsString("'+2348012345678x", $csv);

        $bank = (new CsvBankExporter)->generate(collect([[
            'employee_name' => '-2+3+cmd|/C calc!A0',
            'account_number' => '0123456789',
            'amount' => 1000,
        ]]));
        $this->assertStringContainsString("'-2+3+cmd", $bank);
    }

    public function test_s4_a_re_imported_export_loses_the_escape_mark(): void
    {
        $this->assertSame('=A1', Csv::unescapeCell(Csv::escapeCell('=A1')));
        $this->assertSame('-12.5', Csv::escapeCell('-12.5'));
        $this->assertSame("'plain", Csv::unescapeCell("'plain"));
    }
}
