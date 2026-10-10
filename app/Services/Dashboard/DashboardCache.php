<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\Cache;

/**
 * Saved dashboard figures, per business.
 *
 * Every key carries the business's "version". Posting anything to the books
 * (a journal, invoice, bill, payment, expense, stock change...) moves the
 * version on, so the next visit works the figures out again straight away
 * instead of showing numbers up to ten minutes old. See
 * AppServiceProvider::bumpDashboardOnChange().
 */
class DashboardCache
{
    public static function remember(int $tenantId, string $key, callable $callback): mixed
    {
        $version = (int) Cache::get(self::versionKey($tenantId), 0);

        return Cache::remember(
            "dashboard:{$tenantId}:v{$version}:{$key}",
            (int) config('dashboard.cache_seconds', 600),
            $callback
        );
    }

    public static function bump(?int $tenantId): void
    {
        if (! $tenantId) {
            return;
        }
        $key = self::versionKey($tenantId);
        if (! Cache::has($key)) {
            Cache::forever($key, 1);

            return;
        }
        Cache::increment($key);
    }

    private static function versionKey(int $tenantId): string
    {
        return "dashboard:{$tenantId}:version";
    }
}
