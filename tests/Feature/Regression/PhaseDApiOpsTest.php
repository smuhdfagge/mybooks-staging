<?php

namespace Tests\Feature\Regression;

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
}
