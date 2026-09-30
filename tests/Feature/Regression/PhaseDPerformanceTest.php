<?php

namespace Tests\Feature\Regression;

use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Import;
use App\Models\Invoice;
use App\Services\ExportService;
use App\Services\ImportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    // ── P3: imports and exports on the queue ────────────────────

    /** Drops the signed-in user, as in a queue worker. */
    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_p3_import_is_queued_and_the_job_imports_the_rows(): void
    {
        Queue::fake();
        Storage::fake('imports');
        $this->createAuthenticatedUser(['import data']);

        $csv = "name,email\n";
        for ($i = 1; $i <= 1200; $i++) {
            $csv .= "Customer {$i},c{$i}@example.com\n";
        }
        $path = $this->tenant->id.'/customers.csv';
        Storage::disk('imports')->put($path, $csv);
        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_CUSTOMERS,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_MAPPING,
            'original_filename' => 'customers.csv', 'file_path' => $path, 'file_size' => strlen($csv),
        ]);

        $this->get(route('imports.mapping', $import))->assertOk()->assertSee('c5@example.com');

        $this->post(route('imports.process', $import), ['mapping' => ['name' => 'name', 'email' => 'email']])
            ->assertRedirect(route('imports.show', $import));

        Queue::assertPushed(ProcessImport::class, fn ($job) => $job->import->is($import));
        $this->assertSame(0, Customer::count(), 'Nothing is imported inside the web request.');
        $this->assertSame(Import::STATUS_PROCESSING, $import->fresh()->status);

        // Run the job the way a worker would: nobody signed in.
        $userId = $this->user->id;
        $this->signOut();
        $updates = 0;
        DB::listen(function ($query) use (&$updates) {
            if (str_starts_with($query->sql, 'update "imports"')) {
                $updates++;
            }
        });
        (new ProcessImport($import))->handle(app(ImportService::class));

        $import->refresh();
        $this->assertSame(Import::STATUS_COMPLETED, $import->status);
        $this->assertSame(1200, $import->total_rows);
        $this->assertSame(1200, $import->processed_rows);
        $this->assertSame(1200, $import->successful_rows);
        $this->assertSame(1200, Customer::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        // Progress is saved every 500 rows, not after every row.
        $this->assertLessThan(15, $updates);
        $this->assertTrue(ActivityLog::withoutGlobalScopes()->where('user_id', $userId)->where('model_type', Import::class)->exists());
    }

    public function test_p3_exports_and_backups_are_queued_and_the_job_writes_the_file(): void
    {
        Queue::fake();
        Storage::fake('exports');
        [$other] = $this->createTenantWithSubscription();
        Customer::factory()->create(['tenant_id' => $other->id]);
        $this->createAuthenticatedUser(['export reports']);
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('exports.store'), ['type' => Export::TYPE_CUSTOMERS, 'format' => 'json'])
            ->assertRedirect(route('exports.index'));
        $this->post(route('exports.quick'), ['type' => Export::TYPE_CUSTOMERS, 'format' => 'csv'])
            ->assertRedirect(route('exports.index'));
        $this->post(route('exports.backup.process'), ['format' => 'json', 'included_data' => ['customers', 'vendors']])
            ->assertRedirect(route('exports.index'));

        Queue::assertPushed(ProcessExport::class, 3);
        $exports = Export::orderBy('id')->get();
        $this->assertCount(3, $exports);
        $this->assertTrue($exports->every(fn ($e) => $e->status === Export::STATUS_PENDING));

        $userId = $this->user->id;
        $this->signOut();
        foreach ($exports as $export) {
            (new ProcessExport($export))->handle(app(ExportService::class));
        }

        [$json, $csv, $backup] = $exports->map->fresh()->all();
        $this->assertSame(Export::STATUS_COMPLETED, $json->status);
        $decoded = json_decode(Storage::disk('exports')->get($json->file_path), true);
        $this->assertCount(3, $decoded['data']);
        $this->assertSame(3, $decoded['metadata']['count']);
        $this->assertSame(Storage::disk('exports')->size($json->file_path), $json->file_size);

        $lines = array_filter(explode("\n", Storage::disk('exports')->get($csv->file_path)));
        $this->assertCount(4, $lines); // header + this business's 3 customers

        $data = json_decode(Storage::disk('exports')->get($backup->file_path), true);
        $this->assertCount(3, $data['customers']);
        $this->assertSame([], $data['vendors']);
        $this->assertSame(['customers', 'vendors'], $data['metadata']['included_data']);

        $this->assertSame(3, ActivityLog::withoutGlobalScopes()->where('user_id', $userId)->whereIn('action', [ActivityLog::ACTION_EXPORTED, ActivityLog::ACTION_BACKUP])->count());
    }

    public function test_p3_pdf_exports_are_capped(): void
    {
        Storage::fake('exports');
        $this->createAuthenticatedUser(['export reports']);
        Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $service = new class extends ExportService
        {
            public const PDF_MAX_ROWS = 3;

            public ?int $rows = null;

            public ?bool $truncated = null;

            protected function generateHtmlTable(string $type, array $data, bool $truncated = false): string
            {
                $this->rows = count($data);
                $this->truncated = $truncated;

                return parent::generateHtmlTable($type, $data, $truncated);
            }
        };

        $export = Export::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_PDF, 'status' => Export::STATUS_PENDING,
        ]);
        $this->assertTrue($service->processExport($export));
        $this->assertSame(3, $service->rows);
        $this->assertTrue($service->truncated);
        $this->assertSame(2000, ExportService::PDF_MAX_ROWS);
    }
}
