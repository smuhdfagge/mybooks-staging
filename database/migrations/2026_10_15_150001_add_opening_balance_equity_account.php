<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opening stock posts Dr Inventory, Cr Opening Balance Equity (F1). Every
 * business gets an "Opening Balance Equity" account (3900). If 3900 is
 * already used for something else, the next free code is used and
 * remembered in the business's account mappings.
 *
 * Rerunnable: the account is only added when the business has none.
 */
return new class extends Migration
{
    /** Codes tried in turn for the account. */
    private const CODES = ['3900', '3910', '3920', '3930', '3940', '3950', '3960', '3970', '3980', '3990'];

    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->addAccount((int) $tenantId);
        }
    }

    private function addAccount(int $tenantId): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['settings']);
        $settings = json_decode((string) ($tenant->settings ?? ''), true) ?: [];
        $mapped = $settings['account_mappings']['opening_balance_equity'] ?? null;
        if ($mapped && DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('account_code', $mapped)->exists()) {
            return;
        }

        $accounts = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->whereIn('account_code', self::CODES)->pluck('name', 'account_code');
        $existing = $accounts->get('3900');
        if ($existing !== null && preg_match('/opening/i', (string) $existing)) {
            return; // already there
        }

        $code = collect(self::CODES)->first(fn ($c) => ! $accounts->has($c));
        if (! $code) {
            return; // every code taken; the business can map one in its settings
        }

        DB::table('chart_of_accounts')->insert([
            'tenant_id' => $tenantId,
            'account_code' => $code,
            'name' => 'Opening Balance Equity',
            'type' => 'equity',
            'sub_type' => 'equity',
            'is_system' => true,
            'is_active' => true,
            'current_balance' => 0,
            'opening_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($code !== '3900') {
            $settings['account_mappings']['opening_balance_equity'] = $code;
            DB::table('tenants')->where('id', $tenantId)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        // Left in place: journals may already use it.
    }
};
