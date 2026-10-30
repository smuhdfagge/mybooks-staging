<?php

namespace App\Support;

use App\Exceptions\MissingTenantContext;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\Auth;

/**
 * Which business's rows a query may see (audit MB-26). The tenant scope
 * used to apply only when someone was signed in, so console commands, jobs
 * and webhooks read every business's data. Now a tenant-scoped query needs
 * one of:
 * - a signed-in user with a tenant (web and API requests);
 * - an explicit tenant, set with Tenant::run($id, fn) (commands, webhooks);
 * - an explicit "every business", set with Tenant::acrossAll(fn) (the
 *   admin panel, signed in on its own guard, and cross-business sweeps);
 * otherwise it throws MissingTenantContext instead of reading everything.
 *
 * Queued jobs, mail and notifications carry the context they were queued
 * in (AppServiceProvider), so they run as they would have inline.
 *
 * Held in the container, not a static, so it can't leak from one request
 * or job to the next in a long-lived worker.
 */
class TenantContext
{
    private const STACK = 'tenant.context';

    public const ALL = 'all';

    /** The tenant a query is limited to, or null when there is none (see allowsAll()). */
    public static function id(): ?int
    {
        $set = self::top();
        if ($set !== null) {
            return is_int($set) ? $set : null;
        }

        $user = Auth::user();

        return $user?->tenant_id ? (int) $user->tenant_id : null;
    }

    /** True only inside an explicit Tenant::acrossAll(). */
    public static function allowsAll(): bool
    {
        return self::top() === self::ALL;
    }

    /** What to carry into a queued job: a tenant id, ALL, or null for none. */
    public static function current(): int|string|null
    {
        return self::allowsAll() ? self::ALL : self::id();
    }

    /** The tenant to scope to, throwing when there is none and "every business" wasn't asked for. */
    public static function require(string $model): ?int
    {
        $id = self::id();
        if ($id === null && ! self::allowsAll()) {
            throw new MissingTenantContext($model);
        }

        return $id;
    }

    /**
     * @template T
     *
     * @param  int|'all'|null  $context  null: no business at all (scoped queries throw), even if someone is signed in
     * @param  callable(): T  $callback
     * @return T|null null when the callback returned a PendingDispatch
     */
    public static function run(int|string|null $context, callable $callback): mixed
    {
        self::enter($context);

        try {
            $result = $callback();
            // Job::dispatch() only sends the job when what it returns is
            // dropped, which for a returned value is after this context has
            // ended: send it now, while the context is still set.
            if ($result instanceof PendingDispatch) {
                unset($result);

                return null;
            }

            return $result;
        } finally {
            self::leave();
        }
    }

    /** Start a context; every enter() needs a leave(). Prefer run(). */
    public static function enter(int|string|null $context): void
    {
        $stack = app()->bound(self::STACK) ? app(self::STACK) : [];
        $stack[] = $context === null ? 'none' : ($context === self::ALL ? self::ALL : (int) $context);
        app()->instance(self::STACK, $stack);
    }

    public static function leave(): void
    {
        $stack = app()->bound(self::STACK) ? app(self::STACK) : [];
        array_pop($stack);
        if ($stack === []) {
            app()->forgetInstance(self::STACK);
        } else {
            app()->instance(self::STACK, $stack);
        }
    }

    /** How many contexts are open: for truncate(). */
    public static function depth(): int
    {
        return app()->bound(self::STACK) ? count(app(self::STACK)) : 0;
    }

    /** Close every context opened after depth() returned $depth. */
    public static function truncate(int $depth): void
    {
        while (self::depth() > $depth) {
            self::leave();
        }
    }

    /** The innermost context set, 'none', or null when nothing was set (use the signed-in user). */
    private static function top(): int|string|null
    {
        if (! app()->bound(self::STACK)) {
            return null;
        }
        $stack = app(self::STACK);
        $top = end($stack);

        return $top === false ? null : $top;
    }
}
