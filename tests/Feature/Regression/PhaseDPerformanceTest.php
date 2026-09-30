<?php

namespace Tests\Feature\Regression;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase D: performance at scale (P2-P9).
 */
class PhaseDPerformanceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Runs the request and returns how many queries it made. */
    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // ── P2: analytics ──────────────────────────────────────────

    private function seedAnalytics(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $this->createAuthenticatedUser(['view reports']);
        $tid = $this->tenant->id;

        $a = Customer::factory()->create(['tenant_id' => $tid]);
        $b = Customer::factory()->create(['tenant_id' => $tid]);
        Customer::whereKey($a->id)->update(['created_at' => '2026-01-10 09:00:00']);
        Customer::whereKey($b->id)->update(['created_at' => '2026-06-02 09:00:00']);

        $make = fn (Customer $c, string $date, float $total, string $status, float $paid) => Invoice::withoutEvents(
            fn () => Invoice::factory()->create([
                'tenant_id' => $tid, 'customer_id' => $c->id, 'invoice_date' => $date, 'due_date' => $date,
                'status' => $status, 'subtotal' => $total, 'tax_amount' => 0, 'total' => $total,
                'amount_paid' => $paid, 'balance_due' => $total - $paid,
            ])
        );
        $make($a, '2025-12-20', 1000, 'paid', 1000);
        $make($a, '2026-06-03', 2000, 'partial', 500);
        $make($b, '2026-06-10', 3000, 'unpaid', 0);
        $make($b, '2026-06-10', 4000, 'paid', 4000);

        foreach ([['2026-06-03', 500, $a], ['2026-06-10', 4000, $b]] as $i => [$date, $amount, $customer]) {
            DB::table('payments_received')->insert([
                'tenant_id' => $tid, 'customer_id' => $customer->id, 'payment_number' => 'PR-'.$i,
                'payment_date' => $date, 'amount' => $amount, 'payment_method' => 'cash',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_p2_revenue_chart_uses_a_fixed_number_of_queries_and_same_numbers(): void
    {
        $this->seedAnalytics();
        $this->getJson(route('analytics.chart-data', ['type' => 'status']))->assertOk(); // warm up

        $counts = [];
        foreach ([
            ['period' => 'this_month'],
            ['period' => 'this_year'],
            ['period' => 'custom', 'start_date' => '1900-01-01', 'end_date' => '2100-12-31'],
        ] as $query) {
            Cache::flush();
            $counts[] = $this->countQueries(fn () => $this->getJson(route('analytics.chart-data', ['type' => 'revenue'] + $query))->assertOk());
        }

        // Old code: about 90 for a month and thousands for a long custom range.
        $this->assertLessThanOrEqual(15, max($counts), 'Counts: '.implode(', ', $counts));
        $this->assertSame($counts[0], $counts[2]);

        // Same numbers as the old per-day queries gave.
        $month = $this->getJson(route('analytics.chart-data', ['type' => 'revenue', 'period' => 'this_month']))
            ->assertOk()->json('data');

        $this->assertCount(30, $month['labels']);
        $this->assertSame('03', $month['labels'][2]);
        $this->assertEquals(500, $month['revenue'][2]);
        $this->assertEquals(1, $month['invoice_count'][2]);
        $this->assertEquals(500, $month['payments'][2]);
        $this->assertEquals(4000, $month['revenue'][9]);
        $this->assertEquals(2, $month['invoice_count'][9]);
        $this->assertEquals(4000, $month['payments'][9]);
        $this->assertEquals(4500, array_sum($month['revenue']));

        $year = $this->getJson(route('analytics.chart-data', ['type' => 'revenue', 'period' => 'this_year']))
            ->assertOk()->json('data');
        $this->assertSame('Jun 2026', $year['labels'][5]);
        $this->assertEquals(4500, $year['revenue'][5]);
        $this->assertEquals(3, $year['invoice_count'][5]);
        $this->assertEquals(4500, $year['payments'][5]);
        $this->assertEquals(4500, array_sum($year['revenue']));
    }

    public function test_p2_custom_range_is_capped_and_page_is_cached(): void
    {
        $this->seedAnalytics();

        $response = $this->get(route('analytics.index', ['period' => 'custom', 'start_date' => '1900-01-01', 'end_date' => '2026-06-30']));
        $response->assertOk();
        $this->assertSame('2024-07-01', $response->viewData('startDate'));
        $this->assertTrue($response->viewData('rangeCapped'));
        $this->assertCount(24, $response->viewData('averageOrderValue'));

        // A second visit within 10 minutes is served from the cache.
        $again = $this->countQueries(fn () => $this->get(route('analytics.index', ['period' => 'custom', 'start_date' => '1900-01-01', 'end_date' => '2026-06-30']))->assertOk());
        $fresh = $this->countQueries(function () {
            Cache::flush();
            $this->get(route('analytics.index', ['period' => 'this_year']))->assertOk();
        });
        $this->assertLessThan($fresh - 10, $again);
    }

    public function test_p2_monthly_analytics_are_correct_from_grouped_queries(): void
    {
        $this->seedAnalytics();

        $response = $this->get(route('analytics.index', ['period' => 'custom', 'start_date' => '2025-12-01', 'end_date' => '2026-06-30']));
        $response->assertOk();

        $aov = collect($response->viewData('averageOrderValue'))->keyBy('month');
        $this->assertCount(7, $aov);
        $this->assertEquals(['month' => 'Dec 2025', 'avg_value' => 1000, 'invoice_count' => 1, 'total_value' => 1000], $aov['Dec 2025']);
        $this->assertEquals(['month' => 'Jun 2026', 'avg_value' => 3000, 'invoice_count' => 3, 'total_value' => 9000], $aov['Jun 2026']);
        $this->assertEquals(0, $aov['Mar 2026']['invoice_count']);

        $acq = collect($response->viewData('customerAcquisition'))->keyBy('month');
        $this->assertSame(['month' => 'Dec 2025', 'new_customers' => 0, 'first_time_buyers' => 1], $acq['Dec 2025']);
        $this->assertSame(['month' => 'Jan 2026', 'new_customers' => 1, 'first_time_buyers' => 0], $acq['Jan 2026']);
        $this->assertSame(['month' => 'Jun 2026', 'new_customers' => 1, 'first_time_buyers' => 1], $acq['Jun 2026']);

        // Bounded no matter how many months are shown.
        Cache::flush();
        $short = $this->countQueries(fn () => $this->get(route('analytics.index', ['period' => 'this_month']))->assertOk());
        Cache::flush();
        $long = $this->countQueries(fn () => $this->get(route('analytics.index', ['period' => 'custom', 'start_date' => '2024-07-01', 'end_date' => '2026-06-30']))->assertOk());
        $this->assertLessThanOrEqual($short + 2, $long, "short={$short} long={$long}");
    }
}
