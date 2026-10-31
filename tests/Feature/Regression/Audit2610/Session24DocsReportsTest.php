<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payroll;
use App\Services\Journals\InvoicePosting;
use App\Services\JournalService;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 24 (docs, report performance,
 * maintainability):
 * - MB-35: the README said Laravel 12 / PHP 8.2 and that quotations and
 *   other modules had no screens and were off; its feature table is now
 *   held to config/mybooks.php;
 * - MB-45: the payroll reports loaded every payslip to show and add them
 *   up: they show a page at a time with totals from the database, and the
 *   CSV files are written as the rows are read;
 * - MB-51: the first per-document postings are out of JournalService.
 */
class Session24DocsReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser(['view reports', 'export reports', 'view payroll', 'view invoices']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    // ---- MB-35 ------------------------------------------------------------------

    public function test_the_readme_says_what_the_code_runs_and_switches_on(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertStringContainsString('Laravel 13 (PHP 8.3+), Livewire 4', $readme);
        $this->assertSame('^8.3', $composer['require']['php']);
        $this->assertSame('^13.0', $composer['require']['laravel/framework']);
        $this->assertStringNotContainsString('no screens', $readme);

        // Every switch in config/mybooks.php is in the table, with its real default.
        $source = file_get_contents(config_path('mybooks.php'));
        preg_match_all("/env\\('(MYBOOKS_FEATURE_[A-Z_]+)', (true|false)\\)/", $source, $switches, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(15, count($switches));
        foreach ($switches as [, $name, $default]) {
            $this->assertMatchesRegularExpression('/\| `'.$name.'` \| \**'.($default === 'true' ? 'on' : 'off').'\**/', $readme, $name);
        }
    }

    // ---- MB-45 ------------------------------------------------------------------

    /** $count approved payslips of 1,000 gross / 900 net, one per employee. */
    private function payslips(int $count, string $method = 'bank_transfer'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $employee = Employee::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->id, 'employee_id' => Employee::generateEmployeeId($this->tenant->id),
                'first_name' => 'Staff', 'last_name' => (string) $i, 'hire_date' => '2026-01-05', 'status' => 'active',
            ]);
            Payroll::withoutEvents(fn () => Payroll::create([
                'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => sprintf('PAY-%s-%03d', $method, $i),
                'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30', 'pay_date' => '2026-09-30',
                'basic_salary' => 1000, 'gross_salary' => 1000, 'total_deductions' => 100, 'net_salary' => 900,
                'status' => Payroll::STATUS_APPROVED, 'payment_method' => $method, 'created_by' => $this->user->id,
            ]));
        }
    }

    public function test_payroll_reports_show_a_page_with_totals_over_the_period(): void
    {
        $this->payslips(60);
        $period = ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'];

        $earnings = $this->get(route('reports.employee-earnings', $period))->assertOk();
        $earnings->assertViewHas('payrolls', fn ($page) => $page->count() === 50 && $page->total() === 60);
        $earnings->assertViewHas('totalGross', 60000.0)->assertSee('60 payslips');
        $this->get(route('reports.employee-earnings', $period + ['page' => 2]))
            ->assertViewHas('payrolls', fn ($page) => $page->count() === 10);

        $this->get(route('reports.payroll-summary', $period))->assertOk()
            ->assertViewHas('byEmployee', fn ($page) => $page->count() === 50 && $page->total() === 60)
            ->assertViewHas('totalNet', 54000.0)->assertViewHas('employeeCount', 60);

        $this->get(route('reports.payroll-register', ['month' => '2026-09']))->assertOk()
            ->assertViewHas('payrolls', fn ($page) => $page->count() === 50)
            ->assertViewHas('payslipCount', 60)
            ->assertViewHas('totals', fn ($totals) => $totals['gross_salary'] === 60000.0)
            ->assertViewHas('statusCounts', fn ($counts) => $counts['approved'] === 60);

        $this->get(route('reports.bank-disbursement', $period))->assertOk()
            ->assertViewHas('payrolls', fn ($page) => $page->count() === 50)
            ->assertViewHas('totals', fn ($totals) => $totals['count'] === 60 && $totals['total_net'] === 54000.0);
    }

    public function test_csv_exports_have_every_row_and_follow_the_pages_filters(): void
    {
        $this->payslips(60);
        $this->payslips(3, 'cash');
        $period = ['start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'format' => 'csv'];
        $lines = fn ($response) => count(array_filter(explode("\n", trim($response->streamedContent())))) - 1; // less the header

        $this->assertSame(63, $lines($this->get(route('reports.export.employee-earnings', $period))->assertOk()));
        $this->assertSame(63, $lines($this->get(route('reports.export.payroll-register', ['month' => '2026-09', 'format' => 'csv']))->assertOk()));
        $this->assertSame(63, $lines($this->get(route('reports.export.payroll-summary', $period))->assertOk()));
        // The bank file had ignored "Paid by".
        $this->assertSame(3, $lines($this->get(route('reports.export.bank-disbursement', $period + ['payment_method' => 'cash']))->assertOk()));
    }

    // ---- MB-51 ------------------------------------------------------------------

    public function test_an_invoice_posts_through_its_posting_class_as_before(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'draft']);
        $invoice->items()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 7.5, 'tax_amount' => 375, 'total' => 5375]);
        $invoice = $invoice->fresh();
        $invoice->forceFill(['status' => 'sent'])->saveQuietly();

        $journal = app(JournalService::class)->createInvoiceJournal($invoice);
        $lines = fn (Journal $j) => $j->entries()->with('account')->get()
            ->map(fn ($e) => [$e->account->account_code, (float) $e->debit, (float) $e->credit])->sortBy(0)->values()->all();
        $this->assertSame([['1200', (float) $invoice->total, 0.0], ['2400', 0.0, (float) $invoice->tax_amount], ['4000', 0.0, (float) ($invoice->subtotal - $invoice->discount_amount)]], $lines($journal));

        // Posting the same invoice again through the class keeps the one journal.
        $again = (new InvoicePosting(app(JournalService::class)))->post($invoice);
        $this->assertSame($journal->id, $again->id);
    }
}
