<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Input VAT used to be posted to Prepaid Expenses (1400), so the VAT return
 * couldn't be read from the ledger (finding A5). Every business gets an
 * Input VAT account (1410) and a VAT Payable account (2410) for settling
 * returns. Earlier input VAT stays in 1400; move it with a journal if
 * needed (README, "VAT").
 */
return new class extends Migration
{
    private array $accounts = [
        ['account_code' => '1410', 'name' => 'Input VAT', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
        ['account_code' => '2410', 'name' => 'VAT Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
    ];

    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($this->accounts as $account) {
                $exists = DB::table('chart_of_accounts')
                    ->where('tenant_id', $tenantId)
                    ->where('account_code', $account['account_code'])
                    ->exists();
                if (! $exists) {
                    DB::table('chart_of_accounts')->insert($account + [
                        'tenant_id' => $tenantId,
                        'is_system' => true,
                        'is_active' => true,
                        'current_balance' => 0,
                        'opening_balance' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Left in place: they may hold postings.
    }
};
