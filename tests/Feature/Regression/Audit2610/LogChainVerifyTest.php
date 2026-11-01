<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Services\LogIntegrityService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Follow-up to the October 2026 audit go-live: logs:verify reported
 * "chain break" on a production log nobody had touched. An entry is linked
 * to the entry before it in the whole table, whichever business wrote it,
 * but the check walked one business's entries and wanted each to follow
 * that business's previous one. Now it checks the entry each one names:
 * still in the log and earlier, or it's a break.
 */
class LogChainVerifyTest extends TestCase
{
    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->other = Tenant::factory()->create();
    }

    private function log(Tenant $tenant, string $text): ActivityLog
    {
        $log = Tenant::run($tenant, fn () => ActivityLog::create([
            'tenant_id' => $tenant->id, 'user_name' => 'Test', 'action' => 'created', 'description' => $text,
        ]));
        $this->assertSame($tenant->id, (int) $log->tenant_id);

        return ActivityLog::withoutGlobalScopes()->find($log->id);
    }

    /** Re-sign a row as sign() would have, linked to $previousHash. */
    private function relink(ActivityLog $log, string $previousHash): void
    {
        $log->previous_hash = $previousHash;
        $hash = (fn () => static::computeHash($log))->call(new LogIntegrityService);
        DB::table('activity_logs')->where('id', $log->id)->update(['previous_hash' => $previousHash, 'integrity_hash' => $hash]);
    }

    public function test_other_businesses_entries_in_between_are_not_breaks(): void
    {
        $this->log($this->tenant, 'A1');
        $this->log($this->other, 'B1');
        $this->log($this->tenant, 'A2');
        $this->log($this->other, 'B2');
        $this->log($this->tenant, 'A3');

        foreach ([$this->tenant, $this->other] as $tenant) {
            $result = LogIntegrityService::verifyChain($tenant->id);
            $this->assertSame([], $result['errors']);
        }
        $this->artisan('logs:verify')->doesntExpectOutputToContain('chain break')->assertSuccessful();
    }

    public function test_two_saves_at_once_linking_to_the_same_entry_are_not_breaks(): void
    {
        $first = $this->log($this->tenant, 'A1');
        $this->log($this->other, 'B1');
        // Saved while B1 was still being written: it saw A1 as the latest.
        $this->relink($this->log($this->tenant, 'A2'), $first->integrity_hash);

        $this->assertTrue(LogIntegrityService::verifyChain($this->tenant->id)['valid']);
    }

    public function test_a_deleted_entry_is_still_found(): void
    {
        $this->log($this->tenant, 'A1');
        $gone = $this->log($this->other, 'B1');
        $after = $this->log($this->tenant, 'A2');
        DB::table('activity_logs')->where('id', $gone->id)->delete();

        $result = LogIntegrityService::verifyChain($this->tenant->id);
        $this->assertFalse($result['valid']);
        $this->assertSame(["Entry #{$after->id}: chain break — the entry it follows is no longer in the log (deleted?)."], $result['errors']);
        $this->artisan('logs:verify', ['--tenant' => $this->tenant->id])->assertFailed();
    }

    public function test_a_changed_entry_is_still_found(): void
    {
        $this->log($this->tenant, 'A1');
        $changed = $this->log($this->tenant, 'A2');
        DB::table('activity_logs')->where('id', $changed->id)->update(['description' => 'edited']);

        $this->assertSame(
            ["Entry #{$changed->id}: integrity hash mismatch — possible tampering."],
            LogIntegrityService::verifyChain($this->tenant->id)['errors']
        );
    }

    public function test_an_entry_that_follows_a_later_one_is_a_break(): void
    {
        $first = $this->log($this->tenant, 'A1');
        $later = $this->log($this->tenant, 'A2');
        $this->relink($first, $later->integrity_hash);

        $errors = LogIntegrityService::verifyChain($this->tenant->id)['errors'];
        $this->assertContains("Entry #{$first->id}: chain break — it follows entry #{$later->id}, which comes after it.", $errors);
    }

    public function test_the_oldest_entry_left_after_a_retention_purge_is_not_a_break(): void
    {
        config(['mybooks.retention.activity_log_months' => 84]);
        $purged = $this->log($this->other, 'old');
        $oldest = $this->log($this->tenant, 'kept');
        DB::table('activity_logs')->where('id', $oldest->id)->update(['created_at' => now()->subMonths(84)->addDays(3)]);
        $this->relink($oldest->fresh(), $purged->integrity_hash);
        DB::table('activity_logs')->where('id', $purged->id)->delete();

        $this->assertTrue(LogIntegrityService::verifyChain($this->tenant->id)['valid']);

        // The same gap in a recent entry is a deletion.
        DB::table('activity_logs')->where('id', $oldest->id)->update(['created_at' => now()->subMonths(6)]);
        $this->relink($oldest->fresh(), $purged->integrity_hash);
        $this->assertFalse(LogIntegrityService::verifyChain($this->tenant->id)['valid']);
    }
}
