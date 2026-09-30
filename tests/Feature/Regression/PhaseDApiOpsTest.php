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
}
