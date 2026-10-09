<?php

namespace App\Services\BankFeeds;

use App\Models\BankFeedConnection;
use App\Models\Tenant;

/**
 * How many bank accounts a business may link, from its plan
 * (plans.bank_feed_accounts_limit; empty = no limit).
 */
class BankFeedLimit
{
    public function limit(Tenant $tenant): ?int
    {
        $limit = $tenant->currentPlan()?->bank_feed_accounts_limit;

        return $limit === null ? null : (int) $limit;
    }

    public function used(int $tenantId): int
    {
        return BankFeedConnection::withoutGlobalScopes()->where('tenant_id', $tenantId)->counted()->count();
    }

    public function reached(Tenant $tenant, int $ignoreConnectionId = 0): bool
    {
        $limit = $this->limit($tenant);
        if ($limit === null) {
            return false;
        }

        $used = BankFeedConnection::withoutGlobalScopes()->where('tenant_id', $tenant->id)->counted()
            ->when($ignoreConnectionId, fn ($q) => $q->where('id', '!=', $ignoreConnectionId))->count();

        return $used >= $limit;
    }

    /** "1 of 3 bank accounts linked" */
    public function summary(Tenant $tenant): string
    {
        $limit = $this->limit($tenant);
        $used = $this->used($tenant->id);

        return $limit === null
            ? "{$used} bank ".($used === 1 ? 'account' : 'accounts').' linked.'
            : "{$used} of {$limit} bank ".($limit === 1 ? 'account' : 'accounts').' linked on your plan.';
    }

    public function reachedMessage(Tenant $tenant): string
    {
        $limit = (int) $this->limit($tenant);

        return "Your plan includes {$limit} linked bank ".($limit === 1 ? 'account' : 'accounts').'. Disconnect one or upgrade your plan to link another.';
    }
}
