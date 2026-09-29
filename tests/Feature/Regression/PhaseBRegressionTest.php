<?php

namespace Tests\Feature\Regression;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
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
}
