<?php

namespace Tests\Feature\Regression;

use App\Events\BillDeleting;
use App\Events\BillSaved;
use App\Events\ExpenseDeleting;
use App\Events\ExpensePaid;
use App\Events\InvoiceDeleting;
use App\Events\InvoiceRefundDeleting;
use App\Events\InvoiceSaved;
use App\Events\PaymentMadeCreated;
use App\Events\PaymentMadeDeleted;
use App\Events\PaymentMadeDeleting;
use App\Events\PaymentMadeUpdated;
use App\Events\PaymentReceivedCreated;
use App\Events\PaymentReceivedDeleted;
use App\Events\PaymentReceivedDeleting;
use App\Events\PaymentReceivedUpdated;
use App\Events\PayrollDeleting;
use App\Events\PayrollPaid;
use App\Events\SalesReceiptDeleting;
use App\Events\SalesReceiptSaved;
use App\Models\AdminUser;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Import;
use App\Models\PayrollBatch;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression tests for the Phase 1 fixes (see MyBooks Fix Plan).
 * Each test name carries the finding ID from the assessment reports.
 */
class Phase1RegressionTest extends TestCase
{
    // ── N1: journal listeners must run once ─────────────────────

    public function test_n1_each_journal_event_has_exactly_one_listener(): void
    {
        $events = [
            InvoiceSaved::class,
            InvoiceDeleting::class,
            BillSaved::class,
            BillDeleting::class,
            SalesReceiptSaved::class,
            SalesReceiptDeleting::class,
            PaymentReceivedCreated::class,
            PaymentReceivedUpdated::class,
            PaymentReceivedDeleting::class,
            PaymentReceivedDeleted::class,
            PaymentMadeCreated::class,
            PaymentMadeUpdated::class,
            PaymentMadeDeleting::class,
            PaymentMadeDeleted::class,
            ExpensePaid::class,
            ExpenseDeleting::class,
            PayrollPaid::class,
            PayrollDeleting::class,
            InvoiceRefundDeleting::class,
        ];

        foreach ($events as $event) {
            $this->assertCount(1, Event::getListeners($event), "{$event} should have exactly one listener");
        }
    }

    public function test_n1_paying_an_expense_debits_the_bank_once(): void
    {
        $this->createAuthenticatedUser();
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 10000]);
        $expense = Expense::factory()->approved()->create([
            'tenant_id' => $this->tenant->id,
            'bank_id' => $bank->id,
            'amount' => 1000,
            'total' => 1000,
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertTrue($expense->markAsPaid());
        $this->assertEqualsWithDelta(9000.0, (float) $bank->fresh()->current_balance, 0.001);
    }

    // ── C3: 2FA middleware ──────────────────────────────────────

    public function test_c3_unverified_2fa_session_is_sent_to_the_challenge_not_a_server_error(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $this->user->forceFill([
            'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Signed in (e.g. remember-me) but 2FA not completed in this session
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertSame($this->user->id, session('two_factor:user_id'));

        // The challenge page itself must load
        $this->get(route('two-factor.challenge'))->assertOk();
    }

    public function test_c3_user_can_complete_the_challenge_and_continue(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_c3_verified_2fa_session_passes(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $this->user->forceFill([
            'two_factor_secret' => Crypt::encryptString('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession(['two_factor_verified' => true])
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ── H2: user and role delete ────────────────────────────────

    public function test_h2_deleting_a_user_works(): void
    {
        $this->createAuthenticatedUser(['delete users']);
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->delete(route('settings.users.destroy', $other))->assertRedirect(route('settings.users'));
        $this->assertNull(User::find($other->id));
    }

    public function test_h2_deleting_a_role_works(): void
    {
        $this->createAuthenticatedUser(['delete roles']);
        $role = Role::create(['name' => 'Regression Role', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $this->delete(route('settings.roles.destroy', $role))->assertRedirect();
        $this->assertNull(Role::find($role->id));
    }

    // ── H3 + N3: document numbers unique per tenant ─────────────

    public function test_h3_two_tenants_can_each_have_payroll_batch_one(): void
    {
        [$t1] = $this->createTenantWithSubscription();
        [$t2] = $this->createTenantWithSubscription();

        foreach ([$t1, $t2] as $tenant) {
            PayrollBatch::withoutTenantGuard(fn () => PayrollBatch::create([
                'tenant_id' => $tenant->id,
                'batch_number' => PayrollBatch::generateNumber($tenant->id),
                'pay_period_start' => now(),
                'pay_period_end' => now(),
                'status' => 'draft',
            ]));
        }

        $this->assertSame(2, PayrollBatch::withoutGlobalScopes()->where('batch_number', 'PBN-000001')->count());
    }

    public function test_n3_two_tenants_can_each_have_purchase_order_one(): void
    {
        [$t1] = $this->createTenantWithSubscription();
        [$t2] = $this->createTenantWithSubscription();

        foreach ([$t1, $t2] as $tenant) {
            $vendor = Vendor::factory()->create(['tenant_id' => $tenant->id]);
            PurchaseOrder::withoutTenantGuard(fn () => PurchaseOrder::create([
                'tenant_id' => $tenant->id,
                'vendor_id' => $vendor->id,
                'order_number' => PurchaseOrder::generateNumber($tenant->id),
                'order_date' => now(),
                'status' => 'draft',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]));
        }

        $this->assertSame(2, PurchaseOrder::withoutGlobalScopes()->where('order_number', 'PO-000001')->count());
    }

    public function test_n3_number_is_still_unique_within_one_tenant(): void
    {
        [$t1] = $this->createTenantWithSubscription();
        $vendor = Vendor::factory()->create(['tenant_id' => $t1->id]);
        $make = fn () => PurchaseOrder::withoutTenantGuard(fn () => PurchaseOrder::create([
            'tenant_id' => $t1->id, 'vendor_id' => $vendor->id, 'order_number' => 'PO-000001',
            'order_date' => now(), 'status' => 'draft', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0,
        ]));

        $make();
        $this->expectException(UniqueConstraintViolationException::class);
        $make();
    }

    // ── N2: purchase-order permissions exist and reach admins ───

    public function test_n2_purchase_order_permissions_exist_and_tenant_admin_can_open_purchase_orders(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $this->assertNotNull(Permission::where('name', "{$action} purchase-orders")->first());
        }

        $admin = User::where('email', 'demo@mybooks.local')->firstOrFail();
        $plan = Plan::factory()->create();
        Subscription::factory()->create([
            'tenant_id' => $admin->tenant_id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($admin)->get(route('purchase-orders.index'))->assertOk();
    }

    public function test_n2_migration_grants_purchase_order_access_to_existing_roles(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        foreach (['view bills', 'create bills', 'edit bills', 'delete bills'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Permission::where('name', 'like', '% purchase-orders')->delete();

        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $clerk = Role::create(['name' => 'Tenant Clerk', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $clerk->givePermissionTo(['view bills', 'create bills']);

        $migration = require database_path('migrations/2026_09_28_000002_add_purchase_order_permissions.php');
        $migration->up();

        $this->assertTrue($admin->fresh()->hasPermissionTo('delete purchase-orders'));
        $this->assertTrue($clerk->fresh()->hasPermissionTo('view purchase-orders'));
        $this->assertTrue($clerk->fresh()->hasPermissionTo('create purchase-orders'));
        $this->assertFalse($clerk->fresh()->hasPermissionTo('delete purchase-orders'));
        $this->assertNotNull(Permission::where('name', 'reconcile banks')->first());
    }

    // ── H1: inactive users and tenants ──────────────────────────

    public function test_h1_inactive_user_cannot_log_in(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false, 'password' => 'Secret#Pass123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret#Pass123']);
        $this->assertGuest();
    }

    public function test_h1_user_of_suspended_tenant_cannot_log_in(): void
    {
        [$tenant] = $this->createTenantWithSubscription(['is_active' => false]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => 'Secret#Pass123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret#Pass123']);
        $this->assertGuest();
    }

    public function test_h1_signed_in_user_is_logged_out_when_deactivated(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $this->get(route('customers.index'))->assertOk();

        $this->user->forceFill(['is_active' => false])->save();

        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_h1_signed_in_user_is_logged_out_when_tenant_suspended(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $this->tenant->forceFill(['is_active' => false])->save();

        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_h1_deactivated_user_cannot_keep_using_an_open_livewire_page(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $html = $this->get(route('customers.index'))->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = collect($matches[1])
            ->map(fn ($s) => html_entity_decode($s))
            ->first(fn ($s) => str_contains(json_decode($s, true)['memo']['name'], 'customers'));
        $this->assertNotNull($snapshot, 'customers table component not found on page');

        $payload = ['components' => [['snapshot' => $snapshot, 'updates' => ['search' => 'x'], 'calls' => []]]];
        $update = Livewire::getUpdateUri();

        $this->withHeaders(['X-Livewire' => '1'])->postJson($update, $payload)->assertOk();

        $this->user->forceFill(['is_active' => false])->save();

        // Each real request starts with fresh Livewire state; in one test the
        // middleware-applied flag would carry over (Livewire 4).
        Livewire::flushState();
        $this->withHeaders(['X-Livewire' => '1'])->postJson($update, $payload)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_h1_api_token_of_deactivated_user_is_rejected_and_revoked(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Permission::findOrCreate('view customers', 'web');
        $user->givePermissionTo('view customers');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/customers')->assertOk();

        $user->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/customers')->assertForbidden();
        $this->assertSame(0, $user->tokens()->count());
    }

    // ── H5: Excel import library ────────────────────────────────

    public function test_h5_excel_upload_is_refused_cleanly_while_library_is_missing(): void
    {
        if (Import::excelSupported()) {
            $this->markTestSkipped('phpoffice/phpspreadsheet is installed; Excel import is enabled.');
        }

        $this->createAuthenticatedUser(['import data']);
        $file = UploadedFile::fake()->create('customers.xlsx', 10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->post(route('imports.upload'), ['type' => 'customers', 'file' => $file])
            ->assertSessionHasErrors(['file' => 'Excel import is not available yet. Please save the sheet as CSV and upload that instead.']);
        $this->assertSame(0, Import::count());
    }

    public function test_h5_csv_upload_still_works(): void
    {
        $this->createAuthenticatedUser(['import data']);
        $file = UploadedFile::fake()->createWithContent('customers.csv', "name,email\nAda,ada@example.com\n");

        $this->post(route('imports.upload'), ['type' => 'customers', 'file' => $file])->assertSessionHasNoErrors();
        $this->assertSame(1, Import::count());
    }

    public function test_h5_spreadsheet_library_is_installed(): void
    {
        if (! Import::excelSupported()) {
            $this->markTestSkipped('Run: composer require phpoffice/phpspreadsheet  (then Excel import switches on).');
        }
        $this->assertTrue(class_exists(IOFactory::class));
    }

    // ── M8: seeder must not create demo accounts outside local ──

    public function test_m8_seeder_creates_no_demo_or_super_admin_accounts_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, User::where('email', 'like', '%@mybooks.local')->count());
        $this->assertSame(0, User::where('is_super_admin', true)->count());
        $this->assertSame(0, AdminUser::where('email', 'like', '%@mybooks.local')->count());
        // Roles and permissions are still seeded
        $this->assertNotNull(Role::where('name', 'admin')->first());
    }
}
