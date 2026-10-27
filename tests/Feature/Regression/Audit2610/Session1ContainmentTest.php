<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\AccrualSchedule;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Import;
use App\Models\Journal;
use App\Models\Role;
use App\Models\Vendor;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 1: containment. The dangerous paths are
 * switched off until the real fixes land (sessions 8, 10 and 16), and a
 * read-only command lists data that may already be affected.
 */
class Session1ContainmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser([
            'view accrual-schedules', 'create accrual-schedules', 'edit accrual-schedules',
            'view journals', 'create journals', 'edit journals', 'post journals',
            'view banks', 'reconcile banks', 'view imports', 'create imports',
        ]);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function schedule(array $overrides = []): array
    {
        return $overrides + [
            'type' => AccrualSchedule::TYPE_PREPAID, 'description' => 'Shop rent', 'total_amount' => 120000,
            'start_month' => now()->addMonth()->format('Y-m'), 'months' => 12, 'balance_account_id' => '',
            'pl_account_id' => $this->account('6100')->id,
        ];
    }

    // MB-53 stopgap
    public function test_a_new_schedule_cannot_be_linked_to_a_bill_expense_or_invoice(): void
    {
        $bill = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id]);

        $this->post(route('accrual-schedules.store'), $this->schedule(['source' => "bill:{$bill->id}"]))
            ->assertSessionHasErrors(['source' => 'A schedule can\'t be linked to a bill, expense or invoice. Record the payment with a journal into the prepaid or deferred revenue account first.']);
        $this->post(route('accrual-schedules.store'), $this->schedule(['source_type' => 'bill', 'source_id' => $bill->id]))
            ->assertSessionHasErrors(['source_type', 'source_id']);
        $this->assertSame(0, AccrualSchedule::count());

        $this->get(route('accrual-schedules.create'))->assertOk()
            ->assertDontSee('name="source"', false)
            ->assertSee('Record the payment with a journal into the prepaid or deferred revenue account first');

        $this->post(route('accrual-schedules.store'), $this->schedule())->assertSessionHasNoErrors();
        $this->assertNull(AccrualSchedule::firstOrFail()->source_type);
    }

    public function test_changing_a_schedule_linked_before_the_fix_keeps_its_link(): void
    {
        $this->post(route('accrual-schedules.store'), $this->schedule())->assertSessionHasNoErrors();
        $schedule = AccrualSchedule::firstOrFail();
        $schedule->update(['source_type' => 'expense', 'source_id' => 7]);

        $this->put(route('accrual-schedules.update', $schedule), $this->schedule(['description' => 'Shop rent, renamed']))->assertSessionHasNoErrors();

        $schedule->refresh();
        $this->assertSame(['Shop rent, renamed', 'expense', 7], [$schedule->description, $schedule->source_type, (int) $schedule->source_id]);
    }

    // MB-08 stopgap
    public function test_reconcile_screen_never_says_all_caught_up_while_a_difference_remains(): void
    {
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'chart_of_account_id' => $this->account('1000')->id, 'opening_balance' => 1000, 'current_balance' => 4000]);

        $this->get(route('banks.reconcile', $bank))->assertOk()
            ->assertDontSee('All caught up')
            ->assertSee('No bank lines to reconcile')
            ->assertSee('Lines come from a bank feed');

        $bank->update(['current_balance' => 1000]);
        $this->get(route('banks.reconcile', $bank))->assertOk()->assertSee('All caught up');
    }

    // MB-18 stopgap
    public function test_journals_bulk_update_page_is_gone(): void
    {
        $this->get('/bulk-update')->assertNotFound();
        $this->get(route('journals.index'))->assertOk()->assertDontSee('/bulk-update', false)->assertDontSee('Bulk Update');
    }

    // MB-19 stopgap
    public function test_import_types_without_a_handler_are_not_offered(): void
    {
        $types = Import::getImportTypes();
        foreach ([Import::TYPE_INVOICES, Import::TYPE_BILLS, Import::TYPE_JOURNALS] as $type) {
            $this->assertArrayNotHasKey($type, $types);
        }
        $this->assertArrayHasKey(Import::TYPE_CUSTOMERS, $types);
    }

    public function test_audit_command_lists_affected_data_and_changes_nothing(): void
    {
        // A prepaid balance pushed below zero, as a linked schedule did.
        $this->post(route('journals.store'), [
            'journal_date' => now()->toDateString(), 'description' => 'Released twice',
            'entries' => [
                ['account_id' => $this->account('6100')->id, 'debit' => 30000, 'credit' => 0],
                ['account_id' => $this->account('1400')->id, 'debit' => 0, 'credit' => 30000],
            ],
        ])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::latest('id')->firstOrFail()))->assertSessionHasNoErrors();

        $this->post(route('accrual-schedules.store'), $this->schedule())->assertSessionHasNoErrors();
        AccrualSchedule::firstOrFail()->update(['source_type' => 'bill', 'source_id' => 7]);

        Permission::findOrCreate('edit users', 'web');
        Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id])->givePermissionTo('edit users');

        Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main current', 'chart_of_account_id' => $this->account('1000')->id, 'current_balance' => 4000]);

        $balances = ChartOfAccount::pluck('current_balance', 'id')->all();
        auth()->forgetGuards();

        $this->artisan('mybooks:audit-affected-data')
            ->expectsOutputToContain("Tenant {$this->tenant->id}")
            ->expectsOutputToContain('Schedules linked to a document: 1')
            ->expectsOutputToContain('Prepaid Expenses / Deferred Revenue below zero: 1')
            ->expectsOutputToContain('Custom roles with user, role or report permissions: 1')
            ->expectsOutputToContain('Banks whose balance differs from the ledger: 1')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame($balances, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('current_balance', 'id')->all());
        $this->assertSame('bill', AccrualSchedule::withoutGlobalScopes()->firstOrFail()->source_type);
    }
}
