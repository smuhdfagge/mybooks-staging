<?php

namespace Tests\Feature\Regression;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Services\JournalService;
use App\Services\YearEndCloseService;
use Tests\TestCase;

/**
 * Round 3, Phase B: financial statements and tax.
 */
class PhaseBRegressionTest extends TestCase
{
    /** @var array<string, ChartOfAccount> */
    private array $acct = [];

    private function chart(): void
    {
        $rows = [
            ['1000', 'Cash', 'asset', 'cash'],
            ['1200', 'Accounts Receivable', 'asset', 'accounts_receivable'],
            ['1500', 'Equipment', 'asset', 'fixed_asset'],
            ['1510', 'Accumulated Depreciation', 'asset', 'fixed_asset'],
            ['2350', 'Customer Deposits', 'liability', 'other_current_liability'],
            ['2500', 'Bank Loan', 'liability', 'long_term_liability'],
            ['6300', 'Depreciation', 'expense', 'expense'],
            ['1900', 'Suspense asset', 'asset', null],
            ['2000', 'Accounts Payable', 'liability', 'accounts_payable'],
            ['3000', "Owner's Capital", 'equity', 'equity'],
            ['3200', 'Retained Earnings', 'equity', 'retained_earnings'],
            ['4000', 'Sales', 'income', 'income'],
            ['6100', 'Rent', 'expense', 'expense'],
            ['6200', 'Rent Rebates', 'expense', 'expense'],
        ];
        foreach ($rows as [$code, $name, $type, $sub]) {
            // New businesses get a default chart; make these accounts match the test.
            $this->acct[$code] = ChartOfAccount::updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'account_code' => $code],
                ['name' => $name, 'type' => $type, 'sub_type' => $sub, 'is_active' => true, 'current_balance' => 0, 'opening_balance' => 0],
            );
        }
    }

    /** @param array<int, array{0: string, 1: float, 2: float}> $lines code, debit, credit */
    private function postJournal(string $date, array $lines, string $description = 'Test'): Journal
    {
        $service = app(JournalService::class);
        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => Journal::generateNumber($this->tenant->id),
            'journal_date' => $date,
            'description' => $description,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);
        foreach ($lines as [$code, $debit, $credit]) {
            $service->createEntry($journal, $code, $debit, $credit, $description);
        }
        $journal->updateTotals();
        $journal->save();
        $service->updateAccountBalances($journal);

        return $journal;
    }

    private function balance(string $code): float
    {
        return (float) $this->acct[$code]->fresh()->current_balance;
    }

    /**
     * The example from the round 3 assessment: true profit 3,300.
     */
    private function year2025(): AccountingPeriod
    {
        $this->postJournal('2025-03-01', [['1000', 5000, 0], ['4000', 0, 5000]], 'Sales');
        $cancelled = $this->postJournal('2025-04-01', [['1200', 1000, 0], ['4000', 0, 1000]], 'Sale later cancelled');
        app(JournalService::class)->reverseJournal($cancelled, 'Invoice cancelled');
        // The reversal is dated today (2026); date it inside 2025 like a same-year cancellation.
        Journal::where('reference', 'REV-'.$cancelled->journal_number)->update(['journal_date' => '2025-04-02']);
        $this->postJournal('2025-05-01', [['6100', 2000, 0], ['1000', 0, 2000]], 'Rent');
        $this->postJournal('2025-06-01', [['1000', 300, 0], ['6200', 0, 300]], 'Rent rebate');

        return AccountingPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2025',
            'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'open', 'fiscal_year' => 2025,
        ]);
    }

    // ── A1, A8: year-end close ──────────────────────────────────

    public function test_a1_year_end_close_moves_the_true_profit_and_zeroes_every_account(): void
    {
        $this->createAuthenticatedUser();
        $this->chart();
        $period = $this->year2025();

        $result = app(YearEndCloseService::class)->performYearEndClose($period);

        $this->assertEqualsWithDelta(3300, $result['net_income'], 0.001);
        $this->assertEqualsWithDelta(3300, $this->balance('3200'), 0.001);
        foreach (['4000', '6100', '6200'] as $code) {
            $this->assertEqualsWithDelta(0, $this->balance($code), 0.001, "{$code} should be closed to zero");
        }
        $this->assertTrue($period->fresh()->isLocked());
        $this->assertSame(0, Journal::where('journal_type', 'closing')->where('is_posted', false)->count());
    }

    public function test_a1_close_uses_the_mapped_retained_earnings_account(): void
    {
        $this->createAuthenticatedUser();
        $this->chart();
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'account_code' => '3250', 'name' => 'Accumulated Profit',
            'type' => 'equity', 'sub_type' => 'retained_earnings', 'is_active' => true, 'current_balance' => 0,
        ]);
        $this->tenant->update(['settings' => ['account_mappings' => ['retained_earnings' => '3250']]]);
        $period = $this->year2025();

        app(YearEndCloseService::class)->performYearEndClose($period);

        $this->assertEqualsWithDelta(3300, (float) ChartOfAccount::where('account_code', '3250')->value('current_balance'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('3200'), 0.001);
    }

    public function test_a8_profit_and_loss_for_a_closed_year_still_shows_the_year(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        app(YearEndCloseService::class)->performYearEndClose($this->year2025());

        $response = $this->get(route('reports.profit-loss', ['start_date' => '2025-01-01', 'end_date' => '2025-12-31']))->assertOk();

        $this->assertEqualsWithDelta(5000, $response->viewData('revenue'), 0.001);
        $this->assertEqualsWithDelta(1700, $response->viewData('totalExpenses'), 0.001);
        $this->assertEqualsWithDelta(3300, $response->viewData('netProfit'), 0.001);
    }

    // ── A2: balance sheet ───────────────────────────────────────

    public function test_a2_balance_sheet_uses_balances_at_the_chosen_date(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        $this->postJournal('2026-01-05', [['1000', 10000, 0], ['3000', 0, 10000]], 'Owner invests');
        $this->postJournal('2026-02-10', [['1000', 4000, 0], ['4000', 0, 4000]], 'February sales');
        $this->postJournal('2026-03-15', [['1500', 6000, 0], ['1000', 0, 6000]], 'March equipment');

        $feb = $this->get(route('reports.balance-sheet', ['as_of' => '2026-02-28']))->assertOk();

        $this->assertEqualsWithDelta(14000, $feb->viewData('cashAndBank'), 0.001);
        $this->assertEqualsWithDelta(0, $feb->viewData('fixedAssets'), 0.001);
        $this->assertEqualsWithDelta(4000, $feb->viewData('netIncome'), 0.001);
        $this->assertEqualsWithDelta(0, $feb->viewData('difference'), 0.001);
    }

    public function test_a2_balance_sheet_balances_when_an_earlier_year_was_never_closed(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        $this->year2025(); // not closed
        $this->postJournal('2026-02-01', [['1000', 700, 0], ['4000', 0, 700]], '2026 sales');

        $bs = $this->get(route('reports.balance-sheet', ['as_of' => '2026-06-30']))->assertOk();

        $this->assertEqualsWithDelta(3300, $bs->viewData('priorYearsProfit'), 0.001);
        $this->assertEqualsWithDelta(700, $bs->viewData('netIncome'), 0.001);
        $this->assertEqualsWithDelta($bs->viewData('totalAssets'), $bs->viewData('totalLiabilitiesAndEquity'), 0.001);
        $this->assertEqualsWithDelta(0, $bs->viewData('difference'), 0.001);
    }

    public function test_a2_accounts_without_a_known_sub_type_still_appear(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        $this->postJournal('2026-01-05', [['1900', 2500, 0], ['3000', 0, 2500]], 'Suspense');

        $bs = $this->get(route('reports.balance-sheet', ['as_of' => '2026-01-31']))->assertOk();

        $this->assertEqualsWithDelta(2500, $bs->viewData('totalAssets'), 0.001);
        $this->assertEqualsWithDelta(0, $bs->viewData('difference'), 0.001);
    }

    public function test_a2_balance_sheet_follows_the_business_financial_year(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        $this->tenant->update(['fiscal_year_start' => '2025-07-01']);
        $this->postJournal('2026-05-01', [['1000', 900, 0], ['4000', 0, 900]], 'May: financial year Jul 2025 - Jun 2026');
        $this->postJournal('2026-08-01', [['1000', 100, 0], ['4000', 0, 100]], 'August: next financial year');

        $bs = $this->get(route('reports.balance-sheet', ['as_of' => '2026-08-31']))->assertOk();

        $this->assertEqualsWithDelta(900, $bs->viewData('priorYearsProfit'), 0.001);
        $this->assertEqualsWithDelta(100, $bs->viewData('netIncome'), 0.001);
    }

    public function test_a2_api_balance_sheet_matches_the_web(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        $this->year2025();

        $web = $this->get(route('reports.balance-sheet', ['as_of' => '2026-01-31']));
        $api = $this->getJson('/api/v1/reports/balance-sheet?as_of=2026-01-31')->assertOk()->json('data');

        $this->assertEqualsWithDelta($web->viewData('totalAssets'), $api['assets']['total_assets'], 0.001);
        $this->assertEqualsWithDelta($web->viewData('totalLiabilitiesAndEquity'), $api['total_liabilities_and_equity'], 0.001);
        $this->assertEqualsWithDelta(0, $api['difference'], 0.001);
    }

    // ── A7: cash flow ───────────────────────────────────────────

    public function test_a7_cash_flow_comes_from_cash_account_movements(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        // Before the period: 1,000 in the bank
        $this->postJournal('2026-02-15', [['1000', 1000, 0], ['3000', 0, 1000]], 'Opening capital');
        // March
        $this->postJournal('2026-03-01', [['1200', 5000, 0], ['4000', 0, 5000]], 'Invoice (no cash)');
        $this->postJournal('2026-03-05', [['1000', 3000, 0], ['1200', 0, 3000]], 'Customer pays part');
        $this->postJournal('2026-03-06', [['1000', 800, 0], ['4000', 0, 800]], 'Cash sale (no payment record)');
        $this->postJournal('2026-03-07', [['1000', 500, 0], ['2350', 0, 500]], 'Customer deposit received');
        $this->postJournal('2026-03-08', [['2350', 500, 0], ['1200', 0, 500]], 'Deposit applied (no cash)');
        $this->postJournal('2026-03-10', [['6100', 1200, 0], ['1000', 0, 1200]], 'Rent paid');
        $this->postJournal('2026-03-12', [['1500', 2500, 0], ['1000', 0, 2500]], 'Equipment bought');
        $this->postJournal('2026-03-31', [['6300', 400, 0], ['1510', 0, 400]], 'Depreciation (no cash)');
        $this->postJournal('2026-03-20', [['1000', 4000, 0], ['2500', 0, 4000]], 'Bank loan');

        $cf = $this->get(route('reports.cash-flow', ['start_date' => '2026-03-01', 'end_date' => '2026-03-31']))->assertOk();

        $this->assertEqualsWithDelta(1000, $cf->viewData('beginningCash'), 0.001);
        $this->assertEqualsWithDelta(4300, $cf->viewData('paymentsReceived'), 0.001, 'part payment + cash sale + deposit, once');
        $this->assertEqualsWithDelta(1200, $cf->viewData('expensesPaid'), 0.001);
        $this->assertEqualsWithDelta(3100, $cf->viewData('netOperatingCashFlow'), 0.001);
        $this->assertEqualsWithDelta(2500, $cf->viewData('fixedAssetPurchases'), 0.001);
        $this->assertEqualsWithDelta(0, $cf->viewData('fixedAssetSales'), 0.001, 'depreciation is not a sale of assets');
        $this->assertEqualsWithDelta(4000, $cf->viewData('borrowingsReceived'), 0.001);
        $this->assertEqualsWithDelta(0, $cf->viewData('capitalContributions'), 0.001);
        $this->assertEqualsWithDelta(4600, $cf->viewData('netCashFlow'), 0.001); // 3,100 - 2,500 + 4,000
        $this->assertEqualsWithDelta(5600, $cf->viewData('endingCash'), 0.001);
        $this->assertEqualsWithDelta(5600, (float) $this->acct['1000']->fresh()->current_balance, 0.001, 'closing cash = the cash account');
    }

    public function test_a7_year_end_close_is_not_owners_capital_in_cash_flow(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->chart();
        app(YearEndCloseService::class)->performYearEndClose($this->year2025());

        $cf = $this->get(route('reports.cash-flow', ['start_date' => '2025-01-01', 'end_date' => '2025-12-31']))->assertOk();

        $this->assertEqualsWithDelta(0, $cf->viewData('capitalContributions'), 0.001);
        $this->assertEqualsWithDelta(0, $cf->viewData('drawings'), 0.001);
        $this->assertEqualsWithDelta(3300, $cf->viewData('netCashFlow'), 0.001);
    }

    // ── A4: VAT after discount ──────────────────────────────────

    private function discountedInvoicePayload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'discount_type' => 'percentage',
            'discount_amount' => 10,
            'items' => [
                ['description' => 'Goods A', 'quantity' => 6, 'unit_price' => 10000, 'tax_rate' => 7.5],
                ['description' => 'Goods B', 'quantity' => 4, 'unit_price' => 10000, 'tax_rate' => 7.5],
            ],
        ];
    }

    private function assertDiscountedTotals(Invoice $invoice): void
    {
        $this->assertEqualsWithDelta(100000, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(10000, (float) $invoice->discount_amount, 0.001);
        $this->assertEqualsWithDelta(6750, (float) $invoice->tax_amount, 0.001, 'VAT on the discounted 90,000');
        $this->assertEqualsWithDelta(96750, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(6750, (float) $invoice->items()->sum('tax_amount'), 0.001);
    }

    public function test_a4_web_invoice_charges_vat_after_the_discount(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('invoices.store'), $this->discountedInvoicePayload($customer))->assertSessionHasNoErrors();

        $this->assertDiscountedTotals(Invoice::sole());
    }

    public function test_a4_api_invoice_charges_vat_after_the_discount(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson('/api/v1/invoices', $this->discountedInvoicePayload($customer))->assertCreated();

        $this->assertDiscountedTotals(Invoice::sole());
    }

    public function test_a4_document_discount_is_shared_across_lines_with_different_vat(): void
    {
        $totals = \App\Services\Sales\DocumentTotals::calculate([
            ['quantity' => 1, 'unit_price' => 60000, 'tax_rate' => 7.5],
            ['quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 0], // exempt
        ], 'fixed', 10000);

        // Discount 6,000 / 4,000; VAT only on the first line: 54,000 x 7.5%.
        $this->assertEqualsWithDelta(4050, $totals['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(94050, $totals['total'], 0.001);
    }

    public function test_a4_editing_an_invoice_keeps_its_discount(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices', 'edit invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->post(route('invoices.store'), $this->discountedInvoicePayload($customer));
        $invoice = Invoice::sole();

        // The edit form's discount field must be the one the server reads.
        $html = $this->get(route('invoices.edit', $invoice))->assertOk()->getContent();
        $this->assertStringContainsString('name="discount_amount"', $html);
        $this->assertStringNotContainsString('name="discount_value"', $html);

        $this->put(route('invoices.update', $invoice), $this->discountedInvoicePayload($customer))->assertSessionHasNoErrors();
        $this->assertDiscountedTotals($invoice->fresh());
    }

    // ── A5: VAT return from the ledger ──────────────────────────

    private function vatScenario(): void
    {
        $journals = app(JournalService::class);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $today = now()->toDateString();

        // An unpaid invoice (the old report skipped "unpaid").
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid',
            'invoice_date' => $today, 'subtotal' => 1000, 'tax_amount' => 75, 'total' => 1075, 'balance_due' => 1075,
        ]));
        $invoice->items()->create(['description' => 'Goods', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7.5, 'tax_amount' => 75, 'total' => 1075]);
        $journals->createInvoiceJournal($invoice);

        // An invoice posted then cancelled: its VAT and the reversal cancel out.
        $cancelled = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid',
            'invoice_date' => $today, 'subtotal' => 2000, 'tax_amount' => 150, 'total' => 2150, 'balance_due' => 2150,
        ]));
        $cancelled->items()->create(['description' => 'Goods', 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 7.5, 'tax_amount' => 150, 'total' => 2150]);
        $journals->createInvoiceJournal($cancelled);
        $cancelled->status = 'cancelled';
        $journals->createInvoiceJournal($cancelled);

        // An unpaid bill (the old report skipped it) and an expense.
        $bill = \App\Models\Bill::withoutEvents(fn () => \App\Models\Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid',
            'bill_date' => $today, 'subtotal' => 400, 'tax_amount' => 30, 'total' => 430, 'balance_due' => 430,
        ]));
        $bill->items()->create(['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 400, 'tax_rate' => 7.5, 'tax_amount' => 30, 'total' => 430]);
        $journals->createBillJournal($bill);

        $expense = \App\Models\Expense::withoutEvents(fn () => \App\Models\Expense::factory()->create([
            'tenant_id' => $this->tenant->id, 'expense_date' => $today, 'amount' => 200, 'tax_amount' => 20, 'total' => 220,
            'status' => \App\Models\Expense::STATUS_PAID,
            'expense_account_id' => ChartOfAccount::where('tenant_id', $this->tenant->id)->where('type', 'expense')->value('id'),
        ]));
        $journals->createExpenseJournal($expense);
    }

    public function test_a5_vat_return_counts_every_posted_document_from_the_ledger(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->vatScenario();

        $r = $this->get(route('reports.vat-gst-return', ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()]))->assertOk();

        $this->assertEqualsWithDelta(75, $r->viewData('totalOutputTax'), 0.001);
        $this->assertEqualsWithDelta(50, $r->viewData('totalInputTax'), 0.001, 'bill 30 + expense 20');
        $this->assertEqualsWithDelta(25, $r->viewData('netTaxPayable'), 0.001);
        $this->assertEqualsWithDelta(1000, $r->viewData('totalOutputTaxable'), 0.001);

        // Input VAT has its own account, not Prepaid Expenses.
        $this->assertEqualsWithDelta(50, (float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1410')->value('current_balance'), 0.001);
    }

    public function test_a5_settling_moves_the_net_into_vat_payable_once(): void
    {
        $this->createAuthenticatedUser(['view reports', 'create journals']);
        $this->vatScenario();
        $period = ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];

        $this->post(route('reports.vat-gst-return.settle'), $period)->assertSessionHas('success');
        $this->post(route('reports.vat-gst-return.settle'), $period)->assertSessionHas('error');

        $balance = fn ($code) => (float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance');
        $this->assertEqualsWithDelta(0, $balance('2400'), 0.001);
        $this->assertEqualsWithDelta(0, $balance('1410'), 0.001);
        $this->assertEqualsWithDelta(25, $balance('2410'), 0.001);

        // The return still shows the period's VAT after settling.
        $r = $this->get(route('reports.vat-gst-return', $period));
        $this->assertEqualsWithDelta(25, $r->viewData('netTaxPayable'), 0.001);
        $this->assertNotNull($r->viewData('settlement'));
    }
}
