<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * What /api/v1/health reports (finding O6). It used to say "ok" whatever
 * the state. Each check is "ok", "warn" (worth a look, still serving) or
 * "fail" (the service is broken); any fail makes the endpoint answer 503.
 * Details are kept vague: no hostnames, paths or error text.
 */
class HealthCheck
{
    /** A queued job waiting longer than this means the worker is stuck. */
    public const QUEUE_STUCK_MINUTES = 15;

    /** Backups run daily; allow a couple of hours' slack. */
    public const BACKUP_MAX_AGE_HOURS = 26;

    public function __construct(private BackupService $backups) {}

    /**
     * @return array{healthy: bool, checks: array<string, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->guard(fn () => $this->database()),
            'cache' => $this->guard(fn () => $this->cache()),
            'queue' => $this->guard(fn () => $this->queue()),
            'storage' => $this->guard(fn () => $this->storage()),
            'backup' => $this->guard(fn () => $this->backup()),
        ];

        $healthy = ! in_array('fail', array_column($checks, 'status'), true);

        return ['healthy' => $healthy, 'checks' => $checks];
    }

    /**
     * @param  callable(): array<string, mixed>  $check
     * @return array<string, mixed>
     */
    private function guard(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'fail', 'message' => 'Check failed.'];
        }
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        DB::select('select 1');

        return ['status' => 'ok'];
    }

    /** @return array<string, mixed> */
    private function cache(): array
    {
        $key = 'health-check:'.Str::random(8);
        Cache::put($key, 'ok', 10);
        $ok = Cache::get($key) === 'ok';
        Cache::forget($key);

        return $ok ? ['status' => 'ok'] : ['status' => 'fail', 'message' => 'Cache did not return what was stored.'];
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $result = ['status' => 'ok', 'connection' => (string) config('queue.default')];

        if (config('queue.default') === 'database' && Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))) {
            $table = (string) config('queue.connections.database.table', 'jobs');
            $waiting = DB::table($table)->whereNull('reserved_at')->where('available_at', '<=', now()->getTimestamp());
            $result['pending'] = (clone $waiting)->count();
            $oldest = (clone $waiting)->min('available_at');
            $result['oldest_pending_minutes'] = $oldest ? (int) floor((now()->getTimestamp() - (int) $oldest) / 60) : 0;

            if ($result['oldest_pending_minutes'] > self::QUEUE_STUCK_MINUTES) {
                $result['status'] = 'fail';
                $result['message'] = 'Queued jobs are not being processed.';
            }
        }

        $failedTable = (string) config('queue.failed.table', 'failed_jobs');
        if (Schema::hasTable($failedTable)) {
            $result['failed_last_24h'] = DB::table($failedTable)->where('failed_at', '>=', now()->subDay())->count();
            if ($result['failed_last_24h'] > 0 && $result['status'] === 'ok') {
                $result['status'] = 'warn';
                $result['message'] = 'Some jobs failed in the last 24 hours.';
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        $file = storage_path('app/.health-check-'.Str::random(8));
        $written = @file_put_contents($file, 'ok') !== false;
        if ($written) {
            @unlink($file);
        }

        return $written ? ['status' => 'ok'] : ['status' => 'fail', 'message' => 'Storage is not writable.'];
    }

    /** @return array<string, mixed> */
    private function backup(): array
    {
        if (! config('mybooks.backup.enabled')) {
            return ['status' => 'ok', 'enabled' => false];
        }

        $last = $this->backups->lastSuccess();
        if ($last === null) {
            // A new server has no backup yet; flag it without failing.
            return ['status' => 'warn', 'last_success' => null, 'message' => 'No successful backup yet.'];
        }

        $hours = (int) floor($last->diffInHours(now(), true));
        $result = ['status' => 'ok', 'last_success' => $last->toIso8601String(), 'age_hours' => $hours];
        if ($hours > self::BACKUP_MAX_AGE_HOURS) {
            $result['status'] = 'fail';
            $result['message'] = 'The last successful backup is too old.';
        }

        return $result;
    }
}
