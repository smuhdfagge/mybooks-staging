<?php

namespace Tests\Feature\Regression;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Round 3, Phase D: API and operations.
 */
class PhaseDApiOpsTest extends TestCase
{
    // ── I2: sync pages instead of stopping at 1,000 ────────────

    public function test_i2_sync_pages_past_1000_records_and_says_there_is_more(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $rows = [];
        for ($i = 0; $i < 1003; $i++) {
            $rows[] = [
                'tenant_id' => $this->tenant->id, 'name' => "Customer {$i}", 'is_active' => true,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        $first = $this->getJson('/api/v1/sync?entities=customers')->assertOk()->json('data');
        $this->assertCount(1000, $first['data']['customers']);
        $this->assertTrue($first['has_more']);
        $this->assertTrue($first['paging']['customers']['has_more']);
        // The resume point is not later than anything still to be sent.
        $this->assertLessThanOrEqual(strtotime('2026-01-01 00:00:00'), strtotime($first['sync_timestamp']));

        $cursor = $first['paging']['customers']['next_cursor'];
        $second = $this->getJson('/api/v1/sync/customers?cursor='.urlencode($cursor))->assertOk()->json('data');
        $this->assertCount(3, $second['data']);
        $this->assertFalse($second['has_more']);
        $this->assertNull($second['next_cursor']);

        $ids = array_merge(array_column($first['data']['customers'], 'id'), array_column($second['data'], 'id'));
        $this->assertCount(1003, array_unique($ids));
    }

    public function test_i2_small_pages_reach_every_row_even_with_equal_timestamps(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        $ids = [];
        foreach (['2026-01-01 00:00:00', '2026-01-01 00:00:00', '2026-01-01 00:00:00', '2026-01-02 00:00:00', '2026-01-03 00:00:00'] as $time) {
            $ids[] = DB::table('customers')->insertGetId([
                'tenant_id' => $this->tenant->id, 'name' => 'C', 'is_active' => true, 'created_at' => $time, 'updated_at' => $time,
            ]);
        }

        $seen = [];
        $cursor = null;
        for ($guard = 0; $guard < 10; $guard++) {
            $page = $this->getJson('/api/v1/sync/customers?limit=2'.($cursor ? '&cursor='.urlencode($cursor) : ''))
                ->assertOk()->json('data');
            $seen = array_merge($seen, array_column($page['data'], 'id'));
            if (! $page['has_more']) {
                break;
            }
            $cursor = $page['next_cursor'];
        }

        $this->assertSame($ids, $seen, 'oldest first, each row once');
        $this->getJson('/api/v1/sync/customers?cursor=not-a-cursor')->assertStatus(422);
    }

    // ── I6: API errors are always JSON ──────────────────────────

    public function test_i6_api_errors_are_json_without_an_accept_header(): void
    {
        // Not signed in: was a redirect to the login page.
        $this->get('/api/v1/customers')->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);

        // Unknown route: documented shape, no route or class names.
        $this->get('/api/v1/no-such-thing')->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);

        $this->createAuthenticatedUser(['create customers', 'view customers']);

        // Validation: was a 302 back to the previous page.
        $this->post('/api/v1/customers', [])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors('name');

        // A missing record does not name the model class.
        $response = $this->get('/api/v1/customers/999999')->assertNotFound();
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
        $response->assertJson(['success' => false]);
    }

    public function test_i6_a_broken_rule_is_a_422_not_a_500_with_internals(): void
    {
        $this->createAuthenticatedUser(['create journals', 'view journals']);
        \App\Models\AccountingPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Jan 2026', 'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', 'status' => 'closed', 'fiscal_year' => 2026,
        ]);
        $cash = \App\Models\ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $sales = \App\Models\ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post('/api/v1/journals', [
            'journal_date' => '2026-01-15', 'description' => 'Into a closed month',
            'entries' => [
                ['account_id' => $cash->id, 'debit' => 100],
                ['account_id' => $sales->id, 'credit' => 100],
            ],
        ])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors('journal_date');
    }

    public function test_i6_unexpected_errors_hide_the_internal_message(): void
    {
        config(['app.debug' => false]);
        \Illuminate\Support\Facades\Route::middleware('api')->get('/api/v1/_boom', fn () => throw new \RuntimeException('SQLSTATE secret detail'));

        $response = $this->get('/api/v1/_boom')->assertStatus(500)
            ->assertExactJson(['success' => false, 'message' => 'Server error. Please try again later.']);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    // ── I8: every API group is rate limited ─────────────────────

    public function test_i8_writes_without_their_own_limiter_are_still_limited(): void
    {
        $this->createAuthenticatedUser(['edit journals']);

        // PUT journals/{id} had no write limiter, only the shared 60/min.
        for ($i = 0; $i < 30; $i++) {
            $this->putJson('/api/v1/journals/999999', [])->assertNotFound();
        }
        $this->putJson('/api/v1/journals/999999', [])->assertStatus(429)->assertJson(['success' => false]);

        // Reads are counted separately, so they still work.
        $this->getJson('/api/v1/auth/user')->assertOk();
    }

    public function test_i8_reports_have_their_own_limit(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/reports/tax-summary')->assertOk();
        }
        $this->getJson('/api/v1/reports/tax-summary')->assertStatus(429);
    }

    // ── I7: employee bank details are masked ────────────────────

    private function employeeWithBank(): \App\Models\Employee
    {
        return \App\Models\Employee::withoutEvents(fn () => \App\Models\Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-00001', 'first_name' => 'Aisha', 'last_name' => 'Bello',
            'hire_date' => '2025-01-01', 'status' => 'active', 'salary' => 250000,
            'bank_name' => 'Access Bank', 'bank_account_number' => '0123456789', 'tax_id' => 'TIN-99887766',
        ]));
    }

    public function test_i7_employee_api_masks_bank_details_and_pay_without_payroll_access(): void
    {
        $this->createAuthenticatedUser(['view employees']);
        $employee = $this->employeeWithBank();

        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('****6789', $data['bank_account_number']);
        $this->assertSame('****7766', $data['tax_id']);
        $this->assertNull($data['salary']);
        $this->assertTrue($data['sensitive_masked']);

        $list = $this->getJson('/api/v1/employees')->assertOk()->json('data.0');
        $this->assertSame('****6789', $list['bank_account_number']);
        $this->assertNull($this->getJson('/api/v1/employees/summary')->json('data.total_monthly_salary'));
    }

    public function test_i7_payroll_users_see_full_details(): void
    {
        $this->createAuthenticatedUser(['view employees', 'view payroll', 'edit payroll']);
        $employee = $this->employeeWithBank();

        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('0123456789', $data['bank_account_number']);
        $this->assertSame('TIN-99887766', $data['tax_id']);
        $this->assertEquals(250000, $data['salary']);
        $this->assertFalse($data['sensitive_masked']);
    }

    // ── I5: Idempotency-Key on API writes ───────────────────────

    public function test_i5_a_retry_with_the_same_key_replays_instead_of_creating_twice(): void
    {
        $this->createAuthenticatedUser(['create customers']);
        $body = ['name' => 'Dangote Retail', 'email' => 'buyer@example.com'];
        $headers = ['Idempotency-Key' => 'retry-123'];

        $first = $this->postJson('/api/v1/customers', $body, $headers)->assertCreated();
        $second = $this->postJson('/api/v1/customers', $body, $headers)->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Customer::where('tenant_id', $this->tenant->id)->count());

        // The same key for a different request is refused.
        $this->postJson('/api/v1/customers', ['name' => 'Someone else'], $headers)->assertStatus(422);
        // Without a key, requests behave as before.
        $this->postJson('/api/v1/customers', $body)->assertCreated();
        $this->assertSame(2, Customer::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_i5_keys_are_per_user_and_failed_requests_are_not_kept(): void
    {
        $this->createAuthenticatedUser(['create customers']);
        $headers = ['Idempotency-Key' => 'k-1'];

        $this->postJson('/api/v1/customers', [], $headers)->assertStatus(422);
        // After fixing the request the client can reuse the key.
        $this->postJson('/api/v1/customers', ['name' => 'Fixed'], $headers)->assertCreated();

        $other = $this->createUserForTenant($this->tenant, ['create customers']);
        $this->actingAs($other);
        $this->postJson('/api/v1/customers', ['name' => 'Fixed'], $headers)->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(2, Customer::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_i5_old_keys_are_pruned_on_schedule(): void
    {
        $this->createAuthenticatedUser();
        $old = \App\Models\IdempotencyKey::create(['user_id' => $this->user->id, 'key' => 'old', 'route' => 'POST x', 'request_hash' => str_repeat('a', 64), 'status_code' => 201]);
        $old->forceFill(['created_at' => now()->subHours(25)])->save();
        \App\Models\IdempotencyKey::create(['user_id' => $this->user->id, 'key' => 'new', 'route' => 'POST x', 'request_hash' => str_repeat('a', 64), 'status_code' => 201]);

        $this->artisan('model:prune', ['--model' => [\App\Models\IdempotencyKey::class]])->assertSuccessful();

        $this->assertSame(['new'], \App\Models\IdempotencyKey::pluck('key')->all());

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command;
        $this->assertTrue($events->contains(fn ($c) => str_contains((string) $c, 'model:prune') && str_contains((string) $c, 'IdempotencyKey')));
    }

    // ── O6: a real health check ─────────────────────────────────

    private function lastBackup(?\Carbon\Carbon $at): void
    {
        $this->mock(\App\Services\BackupService::class, fn ($m) => $m->shouldReceive('lastSuccess')->andReturn($at));
    }

    public function test_o6_health_reports_each_part_and_200_when_all_is_well(): void
    {
        config(['mybooks.backup.enabled' => true]);
        $this->lastBackup(now()->subHours(3));

        $this->get('/api/v1/health')->assertOk()
            ->assertJson(['status' => 'ok', 'checks' => [
                'database' => ['status' => 'ok'], 'cache' => ['status' => 'ok'], 'queue' => ['status' => 'ok'],
                'storage' => ['status' => 'ok'], 'backup' => ['status' => 'ok', 'age_hours' => 3],
            ]]);
    }

    public function test_o6_health_is_503_when_the_backup_is_stale(): void
    {
        config(['mybooks.backup.enabled' => true]);
        $this->lastBackup(now()->subHours(40));

        $this->get('/api/v1/health')->assertStatus(503)
            ->assertJson(['status' => 'error', 'checks' => ['backup' => ['status' => 'fail']]]);
    }

    public function test_o6_health_is_503_when_the_queue_is_stuck_and_warns_on_failed_jobs(): void
    {
        config(['mybooks.backup.enabled' => false, 'queue.default' => 'database']);
        DB::table('failed_jobs')->insert([
            'uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
        ]);

        $this->get('/api/v1/health')->assertOk()
            ->assertJson(['checks' => ['queue' => ['status' => 'warn', 'failed_last_24h' => 1]]]);

        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->getTimestamp(), 'created_at' => now()->subMinutes(30)->getTimestamp(),
        ]);

        $this->get('/api/v1/health')->assertStatus(503)
            ->assertJson(['checks' => ['queue' => ['status' => 'fail', 'pending' => 1]]]);
    }

    public function test_o6_housekeeping_jobs_are_scheduled(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => (string) $e->command);

        $this->assertTrue($commands->contains(fn ($c) => str_contains($c, 'queue:prune-failed')));
        $this->assertTrue($commands->contains(fn ($c) => str_contains($c, 'logs:verify')));
    }

    // ── O5: .env.example lists every setting ────────────────────

    public function test_o5_every_env_setting_used_in_config_is_in_env_example(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        preg_match_all('/^#?\s*([A-Z][A-Z0-9_]*)=/m', $example, $m);
        $listed = array_flip($m[1]);

        $used = [];
        $files = array_merge(glob(config_path('*.php')) ?: [], glob(database_path('seeders/*.php')) ?: []);
        foreach ($files as $file) {
            preg_match_all("/env\(\s*'([A-Z0-9_]+)'/", (string) file_get_contents($file), $found);
            foreach ($found[1] as $key) {
                $used[$key] = basename($file);
            }
        }

        $missing = array_diff_key($used, $listed);
        $this->assertSame([], $missing, 'Add these to .env.example: '.implode(', ', array_keys($missing)));
    }

    public function test_o5_env_examples_have_safe_defaults(): void
    {
        foreach (['.env.example', '.env.production.example'] as $name) {
            $content = (string) file_get_contents(base_path($name));
            $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $content, $name);
            $this->assertMatchesRegularExpression('/^LOG_LEVEL=warning$/m', $content, $name);
            $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $content, $name);
        }
    }

    // ── O3: CI covers MariaDB, PHP 8.4 and a dependency audit ───

    public function test_o3_ci_runs_mariadb_php_84_and_composer_audit(): void
    {
        $ci = \Symfony\Component\Yaml\Yaml::parseFile(base_path('.github/workflows/php.yml'));

        $this->assertEqualsCanonicalizing(['8.2', '8.4'], $ci['jobs']['tests']['strategy']['matrix']['php']);
        $this->assertArrayHasKey('mariadb', $ci['jobs']['mariadb']['services']);
        $this->assertSame('mariadb', $ci['jobs']['mariadb']['env']['DB_CONNECTION']);

        $runs = collect($ci['jobs']['tests']['steps'])->pluck('run')->filter()->implode("\n");
        $this->assertStringContainsString('composer audit', $runs);
    }

    // ── P-payroll: bulk create deducts tax like a payroll run ───

    private function ngOfficer(): \App\Models\Employee
    {
        $this->tenant->update(['country' => 'NG']);
        \App\Models\StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);

        $employee = \App\Models\Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-00001', 'first_name' => 'Aisha', 'last_name' => 'Musa',
            'hire_date' => '2026-01-01', 'status' => 'active', 'salary' => 500000, 'annual_rent' => 1200000,
        ]);
        $structure = \App\Models\SalaryStructure::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Officer', 'basic_salary' => 500000, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        $structure->items()->create(['type' => 'allowance', 'name' => 'Transport', 'amount_type' => 'fixed', 'amount' => 50000, 'is_taxable' => true, 'sort_order' => 0]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'Pension (8%)', 'amount_type' => 'percentage', 'amount' => 8, 'is_taxable' => true, 'sort_order' => 1]);
        $employee->update(['salary_structure_id' => $structure->id]);

        return $employee;
    }

    public function test_p_payroll_bulk_create_deducts_paye_like_a_payroll_run(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll', 'delete payroll']);
        $employee = $this->ngOfficer();

        $this->post(route('payroll.bulk-store'), [
            'pay_period_start' => '2026-03-01', 'pay_period_end' => '2026-03-31', 'employee_ids' => [$employee->id],
        ])->assertSessionHasNoErrors()->assertRedirect(route('payroll.index'));
        $bulk = \App\Models\Payroll::where('employee_id', $employee->id)->sole();

        $this->assertGreaterThan(0, (float) $bulk->tax_deduction, 'PAYE is deducted');
        $this->assertLessThan((float) $bulk->gross_salary, (float) $bulk->net_salary);

        // The same employee and month through the payroll run gives the same payslip.
        $bulkFigures = $bulk->only(['basic_salary', 'allowances', 'gross_salary', 'tax_deduction', 'other_deductions', 'total_deductions', 'net_salary']);
        $bulk->forceDelete();
        $this->post(route('payroll.generate'), ['month' => '2026-03', 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $run = \App\Models\Payroll::where('employee_id', $employee->id)->sole();

        foreach ($bulkFigures as $field => $value) {
            $this->assertEqualsWithDelta((float) $run->{$field}, (float) $value, 0.001, $field);
        }
        $this->assertEqualsWithDelta(550000, (float) $run->gross_salary, 0.001);
    }

    // ── O7: Nigeria Data Protection Act ─────────────────────────

    private function adminRole(): \App\Models\Role
    {
        return \App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
    }

    /**
     * A business with data in parent tables, child tables without
     * tenant_id, and user-keyed tables.
     *
     * @return array{0: \App\Models\Tenant, 1: \App\Models\User}
     */
    private function businessWithData(): array
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = $this->createUserForTenant($tenant, ['view customers']);
        $user->assignRole($this->adminRole());
        $user->createToken('phone');
        $role = \App\Models\Role::create(['name' => 'clerk-'.$tenant->id, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->givePermissionTo('view customers');

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]));
        DB::table('invoice_items')->insert(['invoice_id' => $invoice->id, 'description' => 'Goods', 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);
        $journal = \App\Models\Journal::withoutEvents(fn () => \App\Models\Journal::factory()->create(['tenant_id' => $tenant->id]));
        $account = \App\Models\ChartOfAccount::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('journal_entries')->insert(['journal_id' => $journal->id, 'account_id' => $account->id, 'debit' => 100, 'credit' => 0]);
        DB::table('sessions')->insert(['id' => 'sess-'.$tenant->id, 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

        return [$tenant, $user];
    }

    /** @return array<string, int> */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (\Illuminate\Support\Facades\Schema::getTableListing(\Illuminate\Support\Facades\Schema::getCurrentSchemaName(), false) as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    public function test_o7_purge_erases_one_business_and_leaves_the_other_untouched(): void
    {
        [$a, $userA] = $this->businessWithData();
        [$b, $userB] = $this->businessWithData();
        $purger = app(\App\Services\TenantPurger::class);
        $this->assertSame([], $purger->unclassifiedTables(), 'every table is classified');

        // Rows B owns, table by table, before the purge.
        $ownedByB = [];
        foreach ($purger->tenantTables() as $table) {
            $ownedByB[$table] = DB::table($table)->where('tenant_id', $b->id)->count();
        }
        $before = $this->rowCounts();

        $deleted = $purger->purge($a);

        foreach ($purger->tenantTables() as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $a->id)->count(), "{$table} still has A's rows");
            $this->assertSame($ownedByB[$table], DB::table($table)->where('tenant_id', $b->id)->count(), "{$table} lost B's rows");
        }
        $this->assertNull(DB::table('tenants')->where('id', $a->id)->first());
        $this->assertNotNull(DB::table('tenants')->where('id', $b->id)->first());

        // Children and user-keyed rows: A's are gone, B's are still there.
        $this->assertSame(1, DB::table('invoice_items')->count());
        $this->assertSame(1, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $userA->id)->count());
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $userB->id)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $userA->id)->count());
        $this->assertTrue($userB->fresh()->hasRole('admin'));
        $this->assertSame(['sess-'.$b->id], DB::table('sessions')->pluck('id')->all());

        // Shared tables (plans, permissions, ...) are not touched at all.
        $after = $this->rowCounts();
        foreach (\App\Services\TenantPurger::SHARED_TABLES as $table) {
            if ($table !== 'tenants' && isset($before[$table])) {
                $this->assertSame($before[$table], $after[$table], "{$table} count");
            }
        }
        $this->assertNotEmpty($deleted);
    }

    public function test_o7_owner_closes_the_business_and_it_is_erased_after_30_days(): void
    {
        [$tenant, $owner] = $this->businessWithData();
        [$other] = $this->businessWithData();

        // Another admin of the same business is not the owner.
        $second = $this->createUserForTenant($tenant);
        $second->assignRole($this->adminRole());
        $this->actingAs($second)->post(route('settings.close-organisation.store'), [
            'password' => 'password', 'confirm_name' => $tenant->name,
        ])->assertForbidden();

        $this->actingAs($owner);
        $this->post(route('settings.close-organisation.store'), ['password' => 'password', 'confirm_name' => 'wrong'])
            ->assertSessionHasErrors('confirm_name');
        $this->post(route('settings.close-organisation.store'), ['password' => 'password', 'confirm_name' => $tenant->name])
            ->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertTrue($tenant->isClosing());
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $tenant->closure_purge_at->getTimestamp(), 5);
        $request = \App\Models\DataRequest::where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['closure', 'scheduled'], [$request->type, $request->status]);

        // Nothing happens before the 30 days are up.
        $this->travel(29)->days();
        $this->artisan('tenants:purge-closed')->assertSuccessful();
        $this->assertNotNull(DB::table('tenants')->where('id', $tenant->id)->first());

        $this->travel(2)->days();
        $this->artisan('tenants:purge-closed')->assertSuccessful();
        $this->assertNull(DB::table('tenants')->where('id', $tenant->id)->first());
        $this->assertSame(0, DB::table('customers')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('customers')->where('tenant_id', $other->id)->count());
        $this->assertSame('completed', $request->fresh()->status, 'the log survives the erasure');

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => (string) $e->command);
        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'tenants:purge-closed')));
    }

    public function test_o7_closing_can_be_cancelled_within_30_days(): void
    {
        [$tenant, $owner] = $this->businessWithData();
        $this->actingAs($owner)->post(route('settings.close-organisation.store'), ['password' => 'password', 'confirm_name' => $tenant->name]);
        $this->get(route('settings.close-organisation'))->assertOk()->assertSee('Cancel closing');

        $this->delete(route('settings.close-organisation.cancel'))->assertRedirect();
        $this->assertFalse($tenant->fresh()->isClosing());
        $this->assertSame('cancelled', \App\Models\DataRequest::where('tenant_id', $tenant->id)->sole()->status);

        $this->travel(31)->days();
        $this->artisan('tenants:purge-closed')->assertSuccessful();
        $this->assertNotNull(DB::table('tenants')->where('id', $tenant->id)->first());
    }

    public function test_o7_the_last_admin_cannot_be_deleted_or_demoted(): void
    {
        [$tenant, $admin] = $this->businessWithData();
        $manager = $this->createUserForTenant($tenant, ['delete users', 'edit users']);

        // Deleting their own account from the profile page.
        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($admin->fresh());

        // Someone else deleting, demoting or deactivating them.
        $this->actingAs($manager)->delete(route('settings.users.destroy', $admin))->assertSessionHas('error');
        $this->assertNotNull($admin->fresh());
        $this->put(route('settings.users.update', $admin), ['name' => 'A', 'email' => $admin->email, 'is_active' => true, 'roles' => []])
            ->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasRole('admin'));

        // With a second admin, the first can go.
        $manager->assignRole($this->adminRole());
        $this->delete(route('settings.users.destroy', $admin))->assertSessionHas('success');
    }

    public function test_o7_email_addresses_are_masked_in_log_files(): void
    {
        $path = storage_path('logs/o7-mask-test.log');
        @unlink($path);
        config(['logging.channels.o7' => [
            'driver' => 'single', 'path' => $path, 'level' => 'debug',
            'tap' => [\App\Logging\MaskEmailAddresses::class], 'replace_placeholders' => true,
        ]]);
        $this->assertSame([\App\Logging\MaskEmailAddresses::class], config('logging.channels.daily.tap'));

        \Illuminate\Support\Facades\Log::channel('o7')->info('Invoice sent to aisha.bello@example.com', ['to' => 'musa@kano.ng']);
        $content = (string) file_get_contents($path);
        @unlink($path);

        $this->assertStringNotContainsString('aisha.bello@example.com', $content);
        $this->assertStringNotContainsString('musa@kano.ng', $content);
        $this->assertStringContainsString('a***@example.com', $content);
    }

    public function test_o7_privacy_page_lists_sub_processors_and_admins_see_the_request_log(): void
    {
        $this->get(route('privacy-policy'))->assertOk()
            ->assertSee('Sub-processors')->assertSee('Paystack')->assertSee('Tawk.to')->assertSee('Email delivery provider');

        [$tenant, $owner] = $this->businessWithData();
        \App\Models\DataRequest::record('access', $tenant, $owner);
        $admin = \App\Models\AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'admin']);

        $this->actingAsPlatformAdmin($admin)->get(route('admin.data-requests.index'))->assertOk()->assertSee($tenant->name);
        $this->post(route('admin.data-requests.store'), ['type' => 'erasure', 'requester' => 'A customer by email'])->assertRedirect();
        $logged = \App\Models\DataRequest::where('type', 'erasure')->sole();
        $this->patch(route('admin.data-requests.complete', $logged))->assertRedirect();
        $this->assertSame('completed', $logged->fresh()->status);
    }
}
