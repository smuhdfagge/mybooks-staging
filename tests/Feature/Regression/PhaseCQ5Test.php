<?php

namespace Tests\Feature\Regression;

use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\TaxRate;
use Tests\TestCase;

/**
 * Round 3, Phase C, finding Q5: the web form and the API share one form
 * request per endpoint, so a rule holds the same way on both.
 */
class PhaseCQ5Test extends TestCase
{
    private function account(string $type = 'expense', array $attrs = []): ChartOfAccount
    {
        return ChartOfAccount::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'type' => $type], $attrs));
    }

    // ── Banks ───────────────────────────────────────────────────

    public function test_q5_bank_currency_is_a_three_letter_code_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view banks', 'create banks']);
        $bank = ['name' => 'Main', 'bank_name' => 'GTBank', 'account_number' => '0123456789', 'account_type' => 'checking', 'currency' => 'NAIRA'];

        // The API took up to 10 characters; the column holds 3.
        $this->postJson('/api/v1/banks', $bank)->assertStatus(422)->assertJsonValidationErrors('currency');
        $this->post(route('banks.store'), $bank)->assertSessionHasErrors('currency');
        $this->assertSame(0, Bank::count());
    }

    public function test_q5_a_cash_account_needs_no_bank_name_on_web_or_api(): void
    {
        $this->createAuthenticatedUser(['view banks', 'create banks']);
        $cash = ['name' => 'Petty cash', 'account_type' => 'cash', 'currency' => 'NGN'];

        // The API required a bank name and account number; the web did not.
        $this->postJson('/api/v1/banks', $cash)->assertCreated();
        $this->post(route('banks.store'), $cash + ['name' => 'Till'])->assertSessionHasNoErrors();
        $this->assertSame(2, Bank::where('account_type', 'cash')->count());
    }

    // ── Expenses ────────────────────────────────────────────────

    public function test_q5_an_expense_of_zero_is_refused_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'create expenses']);
        $expense = ['expense_account_id' => $this->account()->id, 'name' => 'Fuel', 'expense_date' => '2026-09-01', 'amount' => 0];

        // The API took 0; the web wanted at least 0.01.
        $this->postJson('/api/v1/expenses', $expense)->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->post(route('expenses.store'), $expense)->assertSessionHasErrors('amount');
    }

    public function test_q5_a_new_expense_starts_as_a_draft_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'create expenses']);
        $expense = ['expense_account_id' => $this->account()->id, 'name' => 'Fuel', 'expense_date' => '2026-09-01', 'amount' => 5000];

        // The API could create one already waiting for approval, skipping submit.
        $this->postJson('/api/v1/expenses', $expense + ['status' => 'pending_approval'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson('/api/v1/expenses', $expense + ['status' => 'draft'])->assertCreated();
    }

    public function test_q5_an_expense_waiting_for_approval_cannot_be_changed_on_web_or_api(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'create expenses', 'edit expenses']);
        $this->postJson('/api/v1/expenses', ['expense_account_id' => $this->account()->id, 'name' => 'Fuel', 'expense_date' => '2026-09-01', 'amount' => 5000])->assertCreated();
        $expense = \App\Models\Expense::sole();
        $expense->update(['status' => \App\Models\Expense::STATUS_PENDING_APPROVAL]);

        // The web refused this; the API let the amount change under the approver.
        $this->putJson("/api/v1/expenses/{$expense->id}", ['amount' => 9000])->assertForbidden();
        $this->assertEqualsWithDelta(5000, (float) $expense->fresh()->amount, 0.001);
    }

    // ── Employees and a new employee with no type ───────────────

    public function test_q5_an_api_employee_without_a_type_is_full_time(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees']);
        $this->postJson('/api/v1/employees', ['first_name' => 'Ada', 'last_name' => 'Obi', 'hire_date' => '2026-02-01'])->assertCreated();
        $this->assertSame('full-time', Employee::sole()->employment_type);
    }

    // ── Employees ───────────────────────────────────────────────

    public function test_q5_employee_status_and_salary_type_use_the_values_the_table_holds(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees', 'edit employees']);
        $employee = [
            'first_name' => 'Musa', 'last_name' => 'Garba', 'hire_date' => '2026-02-01',
            'employment_type' => 'full-time',
        ];

        // The API offered "yearly" and "inactive", which the table does not hold.
        $this->postJson('/api/v1/employees', $employee + ['salary_type' => 'yearly'])
            ->assertStatus(422)->assertJsonValidationErrors('salary_type');
        $this->postJson('/api/v1/employees', $employee + ['status' => 'inactive'])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        // ... and refused "on-leave", which the web form offers.
        $this->postJson('/api/v1/employees', $employee + ['salary_type' => 'annual'])->assertCreated();
        $created = Employee::where('first_name', 'Musa')->sole();
        $this->putJson("/api/v1/employees/{$created->id}", ['status' => 'on-leave'])->assertOk();
        $this->assertSame('on-leave', $created->fresh()->status);
    }

    public function test_q5_employee_email_is_optional_and_termination_follows_hire_on_both(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees', 'edit employees']);

        // The API required an email; the web did not.
        $this->postJson('/api/v1/employees', [
            'first_name' => 'Amina', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'employment_type' => 'contract',
        ])->assertCreated();
        $employee = Employee::where('first_name', 'Amina')->sole();

        // The web refused a termination date before the hire date; the API did not.
        $this->putJson("/api/v1/employees/{$employee->id}", ['termination_date' => '2025-12-31'])
            ->assertStatus(422)->assertJsonValidationErrors('termination_date');
        $this->putJson("/api/v1/employees/{$employee->id}", ['termination_date' => '2026-06-30'])->assertOk();
    }

    // ── Journals ────────────────────────────────────────────────

    public function test_q5_a_journal_line_holds_a_debit_or_a_credit_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view journals', 'create journals']);
        $cash = $this->account('asset');
        $sales = $this->account('income');
        $journal = [
            'journal_date' => '2026-09-01',
            'description' => 'Correction',
            'entries' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 100],
                ['account_id' => $sales->id, 'credit' => 100],
            ],
        ];

        // The web took a line with both sides; the API did not.
        $this->post(route('journals.store'), $journal)->assertSessionHasErrors('entries.0');
        $this->postJson('/api/v1/journals', $journal)->assertStatus(422)->assertJsonValidationErrors('entries.0');
        $this->assertSame(0, Journal::count());

        // A blank spare row is dropped on both, as the web form always did.
        $journal['entries'][0]['credit'] = 0;
        $journal['entries'][] = ['account_id' => $sales->id, 'debit' => 0, 'credit' => 0];
        $this->postJson('/api/v1/journals', $journal)->assertCreated();
        $this->post(route('journals.store'), $journal)->assertSessionHasNoErrors();
        $this->assertSame(2, Journal::count());
    }

    public function test_q5_a_journal_needs_a_description_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view journals', 'create journals']);
        $journal = [
            'journal_date' => '2026-09-01',
            'entries' => [
                ['account_id' => $this->account('asset')->id, 'debit' => 100],
                ['account_id' => $this->account('income')->id, 'credit' => 100],
            ],
        ];

        // The web left it optional; the API required it.
        $this->post(route('journals.store'), $journal)->assertSessionHasErrors('description');
        $this->postJson('/api/v1/journals', $journal)->assertStatus(422)->assertJsonValidationErrors('description');

        $this->post(route('journals.store'), $journal + ['description' => 'Opening cash'])->assertSessionHasNoErrors();
        $this->assertSame(1, Journal::count());
    }

    // ── Tax rates ───────────────────────────────────────────────

    public function test_q5_tax_rate_code_is_unique_and_name_short_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view tax-rates', 'create tax-rates', 'edit chart-of-accounts']);
        TaxRate::create(['tenant_id' => $this->tenant->id, 'name' => 'VAT', 'code' => 'VAT', 'rate' => 7.5, 'type' => 'exclusive', 'applies_to' => 'both']);
        $rate = ['name' => 'VAT again', 'code' => 'VAT', 'rate' => 7.5, 'type' => 'exclusive', 'applies_to' => 'sales'];

        // The API ignored the code, so a second VAT went in.
        $this->postJson('/api/v1/tax-rates', $rate)->assertStatus(422)->assertJsonValidationErrors('code');
        $this->post(route('tax-rates.store'), $rate)->assertSessionHasErrors('code');

        // The web took names up to 255 characters, the API up to 100.
        $long = ['name' => str_repeat('A', 150), 'rate' => 5, 'type' => 'exclusive', 'applies_to' => 'sales'];
        $this->post(route('tax-rates.store'), $long)->assertSessionHasErrors('name');
        $this->postJson('/api/v1/tax-rates', $long)->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(1, TaxRate::count());
    }

    // ── Chart of accounts ───────────────────────────────────────

    public function test_q5_an_account_code_already_used_is_refused_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view chart-of-accounts', 'create chart-of-accounts']);
        $this->account('asset', ['account_code' => '9901']);
        $account = ['account_code' => '9901', 'name' => 'Second cash', 'type' => 'asset'];

        // Neither checked; the database refused it with an error page.
        $this->postJson('/api/v1/accounts', $account)->assertStatus(422)->assertJsonValidationErrors('account_code');
        $this->post(route('chart-of-accounts.store'), $account)->assertSessionHasErrors('account_code');
    }

    public function test_q5_an_account_with_journal_lines_keeps_its_type_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['view chart-of-accounts', 'edit chart-of-accounts']);
        $account = $this->account('expense');
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);
        JournalEntry::factory()->debit(100)->create(['journal_id' => $journal->id, 'account_id' => $account->id]);

        // The web let the type change under posted lines; the API did not.
        $this->put(route('chart-of-accounts.update', $account), [
            'account_code' => $account->account_code, 'name' => $account->name, 'type' => 'income',
        ])->assertSessionHasErrors('type');
        $this->putJson("/api/v1/accounts/{$account->id}", ['type' => 'income'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->assertSame('expense', $account->fresh()->type);

        // An unused account may still change type, on both.
        $unused = $this->account('expense');
        $this->putJson("/api/v1/accounts/{$unused->id}", ['type' => 'asset'])->assertOk();
        $this->assertSame('asset', $unused->fresh()->type);
    }

    // ── Items and customers ─────────────────────────────────────

    public function test_q5_the_web_item_form_can_deactivate_an_item_like_the_api(): void
    {
        $this->createAuthenticatedUser(['view items', 'edit items']);
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        // The edit form sends is_active, but the web rules dropped it.
        $this->put(route('items.update', $item), [
            'name' => $item->name, 'type' => 'product', 'selling_price' => 100, 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_q5_a_customer_can_be_added_inactive_on_web_like_the_api(): void
    {
        $this->createAuthenticatedUser(['view customers', 'create customers']);

        $this->postJson('/api/v1/customers', ['name' => 'Old API client', 'is_active' => false])->assertCreated();
        $this->post(route('customers.store'), ['name' => 'Old web client', 'is_active' => 0])->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::where('is_active', false)->count());
    }
}
