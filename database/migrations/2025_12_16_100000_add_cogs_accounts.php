<?php

use App\Models\ChartOfAccount;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Cost of Goods Sold accounts to all existing tenants
        $cogsAccounts = [
            ['account_code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5100', 'name' => 'Purchases', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5200', 'name' => 'Purchase Returns', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5300', 'name' => 'Freight In', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
        ];

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($cogsAccounts as $account) {
                ChartOfAccount::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'account_code' => $account['account_code']],
                    array_merge($account, ['tenant_id' => $tenant->id, 'is_active' => true, 'is_system' => true])
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove COGS accounts
        ChartOfAccount::whereIn('account_code', ['5000', '5100', '5200', '5300'])->delete();
    }
};
