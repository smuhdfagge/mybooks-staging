<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoBusinessDataSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 23 (UX and navigation):
 * - MB-37: a document's activity is in business-date order, with the day
 *   it was recorded where that differs;
 * - MB-38: a new bank gets its own ledger account, named after it;
 * - MB-39: payroll edit shows money as money, totals as you type, and can
 *   work out PAYE;
 * - MB-40, MB-41, MB-50, MB-52: the sidebar by business area (Accounting,
 *   a Tax group, sales in the order the work goes), roomier links, one
 *   component for its groups and links, and the Budgets plan gate in words;
 * - MB-44, MB-46, MB-48, MB-49: panel headings, the loading placeholder as
 *   a status, no "-₦0.00" for no discount, keyboard-scrollable changes.
 *
 * The rendered checks (axe at 1366 and 390 px) were run in a browser
 * against the app; these hold the markup and behaviour in place.
 */
class Session23UxNavigationTest extends TestCase
{
    private const PERMS = [
        'view dashboard', 'view customers', 'view invoices', 'create invoices', 'view sales-orders', 'view sales-receipts', 'view payments-received',
        'view journals', 'view chart-of-accounts', 'view banks', 'create banks', 'edit banks', 'view budgets', 'view vat-returns', 'view withholding-tax',
        'view tax-rates', 'view settings', 'view payroll', 'edit payroll',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser(self::PERMS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    // ---- MB-40 / MB-52: the sidebar --------------------------------------------

    private function sidebar(): string
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        return substr($html, strpos($html, '<aside id="app-sidebar"'), strpos($html, '</aside>') - strpos($html, '<aside id="app-sidebar"'));
    }

    public function test_the_sidebar_reads_by_business_area(): void
    {
        $nav = $this->sidebar();

        $this->assertStringContainsString('aria-label="Main"', $nav);
        $this->assertStringContainsString('Accounting', $nav);
        $this->assertStringNotContainsString('Accountant', $nav);
        // Tax has a group of its own.
        $tax = substr($nav, strpos($nav, '            Tax'));
        foreach (['VAT Return', 'Withholding Tax', 'Tax Rates', 'Tax Groups'] as $label) {
            $this->assertStringContainsString('>'.$label.'</a>', $tax);
        }
        // Sales in the order the work goes, with plural names.
        $positions = array_map(fn ($label) => strpos($nav, '>'.$label.'</a>'), ['Customers', 'Sales Orders', 'Invoices', 'Sales Receipts', 'Payments Received']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertStringNotContainsString('>Payment Received</a>', $nav);
        // Links are at least 36px tall (MB-41).
        $this->assertStringContainsString('flex min-h-9 items-center px-3 py-2 text-sm rounded-lg', $nav);
    }

    public function test_the_budgets_plan_gate_says_what_it_needs(): void
    {
        $nav = $this->sidebar();
        $this->assertStringContainsString('data-testid="budgets-gate"', $nav);
        $this->assertStringContainsString('On Professional and Enterprise. See plans', $nav);
        $this->assertStringContainsString('href="'.route('settings.subscription').'"', $nav);

        $this->plan->update(['slug' => 'professional']);
        $this->actingAs($this->user->fresh());
        $nav = $this->sidebar();
        $this->assertStringNotContainsString('budgets-gate', $nav);
        $this->assertStringContainsString('href="'.route('budgets.index').'"', $nav);
    }

    // ---- MB-37: activity in business order --------------------------------------

    public function test_a_payment_dated_before_it_was_recorded_sits_in_business_order(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'draft',
            'invoice_date' => '2026-09-01', 'due_date' => '2026-09-30']);
        $invoice->items()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 5000]);
        $invoice->fresh()->update(['status' => 'sent']);
        Carbon::setTestNow('2026-10-20 09:00');
        PaymentReceived::withoutEvents(fn () => PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_number' => 'PR-T23',
            'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash',
        ]));
        Carbon::setTestNow();

        $html = $this->get(route('invoices.show', $invoice))->assertOk()->getContent();
        $timeline = substr($html, strpos($html, '<h2 class="section-title">Activity</h2>'));

        $this->assertLessThan(strpos($timeline, 'Payment of'), strpos($timeline, 'Issued'));
        $this->assertStringContainsString('<time datetime="2026-09-15">15 Sep 2026</time>', $timeline);
        $this->assertStringContainsString('Recorded <time datetime="2026-10-20', $timeline);
    }

    public function test_on_the_same_business_date_what_was_recorded_first_comes_first(): void
    {
        $at = fn (string $date, string $recorded, string $text) => ['date' => Carbon::parse($date), 'recorded' => Carbon::parse($recorded), 'text' => $text];
        $html = $this->blade('<x-activity-timeline :events="$events" />', ['events' => [
            $at('2026-09-01', '2026-10-25 09:00', 'Payment'),
            $at('2026-09-01', '2026-10-20 15:00', 'Issued'),
        ]]);

        $html->assertSeeInOrder(['Issued', 'Payment']);
    }

    // ---- MB-38: a bank's own ledger account -------------------------------------

    public function test_a_new_bank_gets_a_ledger_account_named_after_it_that_follows_its_name(): void
    {
        $this->post(route('banks.store'), ['name' => 'Main current', 'bank_name' => 'GTBank', 'account_number' => '0123456789',
            'account_type' => 'checking', 'opening_balance' => 0, 'currency' => 'NGN'])->assertRedirect(route('banks.index'));

        $bank = Bank::where('name', 'Main current')->sole();
        $account = ChartOfAccount::findOrFail($bank->chart_of_account_id);
        $this->assertSame('1120', $account->account_code);
        $this->assertSame('GTBank, Main current (…6789)', $account->name);
        $this->assertSame(['asset', 'bank'], [$account->type, $account->sub_type]);

        $this->put(route('banks.update', $bank), ['name' => 'Operations', 'bank_name' => 'GTBank', 'account_number' => '0123456789',
            'account_type' => 'checking', 'currency' => 'NGN'])->assertRedirect(route('banks.index'));
        $this->assertSame('GTBank, Operations (…6789)', $account->fresh()->name);

        // The next one takes the next code; a credit card is left for a person to link.
        $this->post(route('banks.store'), ['name' => 'Savings', 'account_type' => 'savings', 'currency' => 'NGN']);
        $this->assertSame('1121', ChartOfAccount::findOrFail(Bank::where('name', 'Savings')->value('chart_of_account_id'))->account_code);
        $this->post(route('banks.store'), ['name' => 'Company card', 'account_type' => 'credit_card', 'currency' => 'NGN']);
        $this->assertNull(Bank::where('name', 'Company card')->value('chart_of_account_id'));

        // A deleted account keeps its code: the next bank goes past it.
        ChartOfAccount::create(['tenant_id' => $this->tenant->id, 'account_code' => '1122', 'name' => 'Old bank', 'type' => 'asset', 'sub_type' => 'bank'])->delete();
        $this->post(route('banks.store'), ['name' => 'Payroll', 'account_type' => 'checking', 'currency' => 'NGN'])->assertRedirect(route('banks.index'));
        $this->assertSame('1123', ChartOfAccount::findOrFail(Bank::where('name', 'Payroll')->value('chart_of_account_id'))->account_code);
    }

    // ---- MB-39: payroll edit -------------------------------------------------------

    private function payroll(): Payroll
    {
        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => Employee::generateEmployeeId($this->tenant->id),
            'first_name' => 'Amina', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'status' => 'active',
        ]);

        return Payroll::withoutEvents(fn () => Payroll::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => 'PAY-T23',
            'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30', 'pay_date' => '2026-09-30',
            'basic_salary' => 150000, 'allowances' => 0, 'gross_salary' => 150000, 'tax_deduction' => 0, 'other_deductions' => 8000,
            'total_deductions' => 8000, 'net_salary' => 142000, 'status' => 'draft', 'created_by' => $this->user->id,
            'deduction_details' => [
                ['name' => 'Pension (employee 8%)', 'amount' => 8000, 'pre_tax' => true],
                ['name' => '_tax_method', 'method' => 'flat', 'breakdown' => [['bracket' => 'Flat Rate', 'rate' => 10, 'taxable' => 0, 'tax' => 0]]],
            ],
        ]));
    }

    public function test_payroll_edit_shows_money_as_money_and_takes_it_back(): void
    {
        $payroll = $this->payroll();

        $this->get(route('payroll.edit', $payroll))->assertOk()
            ->assertSee('value="150,000.00"', false)
            ->assertSee('x-data="payslipForm()"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('data-testid="calculate-paye"', false)
            ->assertDontSee('type="number" name="basic_salary"', false)
            // Enter saves: the first submit button in the form is Update, not Calculate PAYE.
            ->assertSeeInOrder(['<button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Update payroll</button>', 'data-testid="calculate-paye"'], false);

        $this->put(route('payroll.update', $payroll), ['pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'basic_salary' => '₦160,000.00', 'allowances' => '5,000', 'tax_deduction' => '1,000.50', 'other_deductions' => '8,000'])
            ->assertSessionHasNoErrors();
        $payroll->refresh();
        $this->assertSame(['160000.00', '165000.00', '9000.50', '155999.50'],
            [$payroll->basic_salary, $payroll->gross_salary, $payroll->total_deductions, $payroll->net_salary]);
    }

    public function test_calculate_paye_fills_the_box_without_saving(): void
    {
        $payroll = $this->payroll();

        // 10% flat on 200,000 less the 8,000 pension relief.
        $this->from(route('payroll.edit', $payroll))->put(route('payroll.update', $payroll), ['pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'basic_salary' => '200,000.00', 'tax_deduction' => '0', 'other_deductions' => '8,000', 'calculate_paye' => '1'])
            ->assertRedirect(route('payroll.edit', $payroll))
            ->assertSessionHas('success', 'PAYE on this pay is '.Money::format(19200).'. Check it, then save.')
            ->assertSessionHasInput('tax_deduction', '19200.00')
            ->assertSessionHasInput('basic_salary', '200000.00');

        $this->assertSame('150000.00', $payroll->fresh()->basic_salary);

        // A percentage relief (8% pension) is taken again on the edited pay: 10% of (300,000 - 24,000).
        $payroll->update(['deduction_details' => [
            ['name' => 'Pension (employee 8%)', 'amount_type' => 'percentage', 'rate' => 8, 'amount' => 12000, 'pre_tax' => true, '_statutory' => 'pension'],
            ['name' => '_tax_method', 'method' => 'flat', 'breakdown' => [['bracket' => 'Flat Rate', 'rate' => 10, 'taxable' => 0, 'tax' => 0]]],
        ]]);
        $this->from(route('payroll.edit', $payroll))->put(route('payroll.update', $payroll), ['pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'basic_salary' => '300,000.00', 'calculate_paye' => '1'])
            ->assertSessionHasInput('tax_deduction', '27600.00');

        // With no tax table and no flat rate there is nothing to work it out from: the amount typed stays.
        $payroll->update(['deduction_details' => []]);
        $this->from(route('payroll.edit', $payroll))->put(route('payroll.update', $payroll), ['pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'basic_salary' => '200,000.00', 'tax_deduction' => '35,000.00', 'calculate_paye' => '1'])
            ->assertRedirect(route('payroll.edit', $payroll))
            ->assertSessionHas('error')
            ->assertSessionHasInput('tax_deduction', '35000.00');
    }

    // ---- MB-44 / MB-46 / MB-48 / MB-49 ------------------------------------------------

    public function test_no_minus_nothing_for_no_discount_and_the_loading_cards_are_a_status(): void
    {
        $this->get(route('invoices.create'))->assertOk()
            ->assertDontSee('>-'.Money::format(0).'<', false)
            ->assertSee("discount > 0 ? '-' + \$money(discount) : \$money(0)", false);

        $this->assertStringContainsString('role="status" aria-busy="true" aria-label="Loading more cards"',
            view('livewire.dashboard.lower-cards-placeholder')->render());
    }

    public function test_money_typed_in_a_form_is_read_as_a_number(): void
    {
        $this->assertSame('150000.50', Money::fromInput('₦150,000.50'));
        $this->assertSame('-1200', Money::fromInput('-1,200'));
        $this->assertSame('12abc', Money::fromInput('12abc'));
        foreach (['abc12', '150.000,50', '1e3', '1,20,000'] as $unclear) {
            $this->assertSame($unclear, Money::fromInput($unclear), 'left for validation to refuse');
        }
        $this->assertSame('2500', Money::fromInput('NGN 2,500'));
        $this->assertSame(5, Money::fromInput(5));
    }

    // ---- the demo business seeds (its stock now has a cost) -----------------------------

    public function test_the_demo_business_seeds(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoBusinessDataSeeder::class);

        $this->assertDatabaseHas('inventory_layers', ['reference_type' => 'opening_stock', 'quantity' => 40]);
    }
}
