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
}
