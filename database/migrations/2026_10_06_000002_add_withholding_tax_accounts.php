<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ledger accounts for withholding tax, for every existing business (new
 * ones get them from ChartOfAccountService): WHT deducted from vendors and
 * owed to the tax authority (2420), WHT our customers deducted from us,
 * held as credit notes to set against income tax (1430), and the income tax
 * those credits are used against (2430).
 */
return new class extends Migration
{
    private array $accounts = [
        ['account_code' => '1430', 'name' => 'WHT Credit Notes Receivable', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
        ['account_code' => '2420', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
        ['account_code' => '2430', 'name' => 'Income Tax Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
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
