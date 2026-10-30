<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Exceptions\MissingTenantContext;
use App\Jobs\ProcessExport;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Throwable;

/**
 * October 2026 audit, fix session 20, MB-26: the tenant scope applied only
 * when someone was signed in, so commands, jobs and webhooks read every
 * business's rows. Now a tenant-scoped query needs a context (a signed-in
 * user, Tenant::run(), or an explicit Tenant::acrossAll()) and throws
 * without one. Every test's artisan() call runs signed out, as cron does
 * (Tests\PendingCommandAsCron).
 */
class Session20TenantContextTest extends TestCase
{
    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a] = $this->createTenantWithSubscription();
        [$this->b] = $this->createTenantWithSubscription();
        foreach ([$this->a, $this->b] as $tenant) {
            Tenant::run($tenant, fn () => Customer::factory()->count($tenant->is($this->a) ? 2 : 3)->create(['tenant_id' => $tenant->id]));
        }
    }

    public function test_a_tenant_scoped_query_with_no_context_throws_instead_of_reading_everything(): void
    {
        $this->assertGuest();

        try {
            Customer::count();
            $this->fail('read every business with nobody signed in');
        } catch (MissingTenantContext $e) {
            $this->assertSame('Customer', $e->model);
            $this->assertStringContainsString('Tenant::run', $e->getMessage());
        }
    }

    public function test_a_signed_in_user_without_a_business_gets_nothing_either(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => null]));

        $this->expectException(MissingTenantContext::class);
        Customer::count();
    }

    public function test_run_limits_queries_to_one_business_and_across_all_says_so_on_purpose(): void
    {
        $this->assertSame(2, Tenant::run($this->a, fn () => Customer::count()));
        $this->assertSame(3, Tenant::run($this->b->id, fn () => Customer::count()));
        $this->assertSame(5, Tenant::acrossAll(fn () => Customer::count()));

        // Nested contexts unwind back to the outer one, and then to none.
        Tenant::acrossAll(function () {
            $this->assertSame(2, Tenant::run($this->a, fn () => Customer::count()));
            $this->assertSame(5, Customer::count());
        });
        $this->expectException(MissingTenantContext::class);
        Customer::count();
    }

    public function test_a_signed_in_user_still_sees_only_their_business_and_run_overrides_it_for_the_call(): void
    {
        $this->actingAs($this->createUserForTenant($this->a));
        $this->assertSame(2, Customer::count());
        $this->assertSame(3, Tenant::run($this->b, fn () => Customer::count()));
        $this->assertSame(2, Customer::count());
    }

    public function test_a_model_made_inside_run_belongs_to_that_business(): void
    {
        $customer = Tenant::run($this->b, fn () => Customer::create(['name' => 'Made in a job']));

        $this->assertSame($this->b->id, $customer->tenant_id);
    }

    public function test_a_queued_job_runs_in_the_context_it_was_queued_in(): void
    {
        config(['queue.default' => 'database']);

        Tenant::run($this->b, fn () => S20CountCustomers::dispatch('in b'));
        Tenant::acrossAll(fn () => S20CountCustomers::dispatch('all'));
        $this->actingAs($this->createUserForTenant($this->a));
        S20CountCustomers::dispatch('signed in to a');
        auth()->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(3, Cache::get('s20:in b'));
        $this->assertSame(5, Cache::get('s20:all'));
        $this->assertSame(2, Cache::get('s20:signed in to a'));
    }

    public function test_a_job_queued_with_no_context_fails_closed_and_one_queued_before_the_change_runs_as_before(): void
    {
        config(['queue.default' => 'database']);

        S20CountCustomers::dispatch('none');
        // A payload written before this change has no tenant_context at all.
        Tenant::run($this->a, fn () => S20CountCustomers::dispatch('legacy'));
        $row = DB::table('jobs')->orderByDesc('id')->first();
        $payload = json_decode($row->payload, true);
        unset($payload['tenant_context']);
        DB::table('jobs')->where('id', $row->id)->update(['payload' => json_encode($payload)]);

        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();

        $this->assertNull(Cache::get('s20:none'));
        $this->assertStringContainsString('MissingTenantContext', (string) DB::table('failed_jobs')->value('exception'));
        $this->assertSame(5, Cache::get('s20:legacy'));
    }

    public function test_a_job_whose_user_is_gone_is_not_run_with_nobody_and_says_why(): void
    {
        $user = $this->createUserForTenant($this->a);
        $export = Tenant::run($this->a, fn () => Export::create([
            'tenant_id' => $this->a->id, 'user_id' => $user->id, 'type' => Export::TYPE_CUSTOMERS,
            'format' => 'csv', 'status' => Export::STATUS_PENDING,
        ]));
        $user->forceFill(['tenant_id' => null])->save();

        try {
            ProcessExport::dispatchSync($export);
            $this->fail('ran without its user');
        } catch (Throwable $e) {
            $this->assertStringContainsString('no longer exists or has no business', $e->getMessage());
        }
        $this->assertSame(Export::STATUS_FAILED, Export::withoutGlobalScopes()->find($export->id)->status);
    }

    /**
     * Every scheduled and repair command, signed out as from cron, with two
     * businesses' data: none may hit a tenant-scoped query without saying
     * which business (the plan's caution for MB-26). key:rotate (writes
     * .env), the backups and the site files are left out: they read no
     * business's rows.
     */
    public function test_every_command_runs_signed_out_without_a_missing_context(): void
    {
        foreach ([$this->a, $this->b] as $tenant) {
            $this->createUserForTenant($tenant, ['view bills', 'view inventory', 'view invoices']);
            Tenant::run($tenant, function () use ($tenant) {
                $customer = Customer::query()->firstOrFail();
                Invoice::factory()->sent()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'due_date' => now()->addDays(2)]);
                Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
                $vendor = Vendor::factory()->create(['tenant_id' => $tenant->id]);
                Bill::factory()->create(['tenant_id' => $tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'due_date' => now()->addDays(3)]);
                Item::factory()->product()->create(['tenant_id' => $tenant->id, 'track_inventory' => true, 'reorder_level' => 10]);
            });
        }

        $missing = [];
        Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$missing) {
            if (str_contains($log->message.json_encode($log->context), 'queried with no tenant')) {
                $missing[] = $log->message;
            }
        });

        $commands = [
            'mybooks:audit-affected-data' => [], 'subscriptions:auto-renew' => [], 'mybooks:check-encrypted' => [],
            'billing:check-paystack' => ['--offline' => true], 'journals:cleanup' => ['--dry-run' => true],
            'mybooks:ensure-admin-roles' => ['--dry-run' => true], 'quotations:expire' => [], 'subscriptions:expire' => [],
            'payroll:fix-journals' => ['tenant_id' => $this->a->id], 'subscriptions:grace' => ['--dry-run' => true],
            'journals:post-reversals' => [], 'stock:post-missing-adjustment-journals' => [], 'assets:post-missing-disposal-journals' => [],
            'transactions:process-recurring' => [], 'tenants:purge-closed' => ['--dry-run' => true],
            'retention:purge' => ['--dry-run' => true, '--force' => true], 'accounts:recalculate' => ['--dry-run' => true],
            'bills:receive-pending-stock' => [], 'bills:reclassify-lines' => ['--dry-run' => true], 'journals:regenerate-invoices' => [],
            'accruals:release' => [], 'accruals:repair-setup' => [], 'einvoice:retry' => [], 'tenants:seed-accounts' => [],
            'notifications:send-bill-reminders' => [], 'notifications:send-low-stock-alerts' => [], 'notifications:send-payment-reminders' => [],
            'messages:send-queued' => [], 'bankfeeds:sync' => [], 'logs:verify' => [],
        ];
        $listed = collect(Artisan::all())->keys()->filter(fn ($name) => str_starts_with((string) get_class(Artisan::all()[$name]), 'App\\Console\\Commands\\'));
        $this->assertEqualsCanonicalizing(
            $listed->reject(fn ($name) => in_array($name, ['key:rotate', 'mybooks:backup', 'mybooks:backup:verify', 'rebrand:colours', 'site:search-files'], true))->values()->all(),
            array_keys($commands),
            'a new command in app/Console/Commands needs adding here'
        );

        foreach ($commands as $command => $arguments) {
            try {
                $this->artisan($command, $arguments)->run();
            } catch (MissingTenantContext $e) {
                $missing[] = "{$command}: {$e->getMessage()}";
            }
        }

        $this->assertSame([], $missing);
    }
}

/** Records how many customers it can see, under a name. */
class S20CountCustomers implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $name) {}

    public function handle(): void
    {
        Cache::put('s20:'.$this->name, Customer::count());
    }
}
