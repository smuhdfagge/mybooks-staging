<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\AccrualSchedule;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\Role;
use App\Services\JournalService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 1: containment. The dangerous paths are
 * switched off until the real fixes land (sessions 8, 10 and 16), and a
 * read-only command lists data that may already be affected. MB-19 (import
 * types without a reader) is fixed on main by the new importers; see
 * tests/Feature/Imports/ImportTypesOnOfferTest.
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

    // MB-53: the stopgap (no linking) is replaced by the setup journal in
    // session 8; see Session8ScheduleSetupTest.

    // MB-08 stopgap
    public function test_reconcile_screen_never_says_all_caught_up_while_a_difference_remains(): void
    {
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'chart_of_account_id' => $this->account('1000')->id, 'opening_balance' => 1000, 'current_balance' => 1000]);
        $this->get(route('banks.reconcile', $bank))->assertOk()->assertSee('All caught up');

        // Money in through the ledger (session 9: the book balance is the ledger's).
        app(JournalService::class)->postLines($bank, 'DEP-1', now()->toDateString(), 'Deposit', [
            ['1000', 3000.0, 0.0, 'Deposit'], ['4000', 0.0, 3000.0, 'Deposit'],
        ], $this->user->id);

        $this->get(route('banks.reconcile', $bank))->assertOk()
            ->assertDontSee('All caught up')
            ->assertSee('No bank lines to reconcile')
            ->assertSee('Lines come from a bank feed');
    }

    // MB-18 stopgap
    public function test_journals_bulk_update_page_is_gone(): void
    {
        $this->get('/bulk-update')->assertNotFound();
        $this->get(route('journals.index'))->assertOk()->assertDontSee('/bulk-update', false)->assertDontSee('Bulk Update');
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

        // A schedule linked before session 8: no setup journal.
        AccrualSchedule::create([
            'tenant_id' => $this->tenant->id, 'schedule_number' => 'PDS-000001', 'start_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'balance_account_id' => $this->account('1400')->id, 'status' => AccrualSchedule::STATUS_ACTIVE, 'released_amount' => 0,
            'source_type' => 'bill', 'source_id' => 7,
        ] + $this->schedule());

        Permission::findOrCreate('edit users', 'web');
        Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id])->givePermissionTo('edit users');

        Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main current', 'chart_of_account_id' => $this->account('1000')->id, 'current_balance' => 4000]);

        $balances = ChartOfAccount::pluck('current_balance', 'id')->all();
        auth()->forgetGuards();

        $this->artisan('mybooks:audit-affected-data')
            ->expectsOutputToContain("Tenant {$this->tenant->id}")
            ->expectsOutputToContain('Schedules linked to a document without a setup journal: 1')
            ->expectsOutputToContain('Prepaid Expenses / Deferred Revenue below zero: 1')
            ->expectsOutputToContain('Custom roles with user, role or report permissions: 1')
            ->expectsOutputToContain('Banks whose balance differs from the ledger: 1')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertSuccessful();

        $this->assertSame($balances, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('current_balance', 'id')->all());
        $this->assertSame('bill', AccrualSchedule::withoutGlobalScopes()->firstOrFail()->source_type);
    }
}
