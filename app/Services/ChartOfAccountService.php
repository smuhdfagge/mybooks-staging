<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Tenant;

class ChartOfAccountService
{
    /**
     * Get the default chart of accounts structure
     */
    public static function getDefaultAccounts(): array
    {
        return [
            // Assets (1000-1999)
            ['account_code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'sub_type' => 'cash'],
            ['account_code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset', 'sub_type' => 'cash'],
            ['account_code' => '1100', 'name' => 'Checking Account', 'type' => 'asset', 'sub_type' => 'bank'],
            ['account_code' => '1110', 'name' => 'Savings Account', 'type' => 'asset', 'sub_type' => 'bank'],
            ['account_code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'sub_type' => 'accounts_receivable'],
            ['account_code' => '1250', 'name' => 'Employee Advances', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
            ['account_code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'sub_type' => 'inventory'],
            ['account_code' => '1400', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
            ['account_code' => '1500', 'name' => 'Equipment', 'type' => 'asset', 'sub_type' => 'fixed_asset'],
            ['account_code' => '1510', 'name' => 'Furniture & Fixtures', 'type' => 'asset', 'sub_type' => 'fixed_asset'],
            ['account_code' => '1520', 'name' => 'Vehicles', 'type' => 'asset', 'sub_type' => 'fixed_asset'],
            ['account_code' => '1600', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'sub_type' => 'fixed_asset'],

            // Liabilities (2000-2999)
            ['account_code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'sub_type' => 'accounts_payable'],
            ['account_code' => '2100', 'name' => 'Credit Card Payable', 'type' => 'liability', 'sub_type' => 'credit_card'],
            ['account_code' => '2200', 'name' => 'Accrued Expenses', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2210', 'name' => 'Accrued Salaries', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2300', 'name' => 'Payroll Liabilities', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2310', 'name' => 'Tax Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2320', 'name' => 'Pension Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2330', 'name' => 'Insurance Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2340', 'name' => 'Union Dues Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2350', 'name' => 'Customer Deposits', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2360', 'name' => 'Garnishments Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2400', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2500', 'name' => 'Short-term Loans', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
            ['account_code' => '2600', 'name' => 'Long-term Loans', 'type' => 'liability', 'sub_type' => 'long_term_liability'],

            // Equity (3000-3999)
            ['account_code' => '3000', 'name' => 'Owner\'s Capital', 'type' => 'equity', 'sub_type' => 'equity'],
            ['account_code' => '3100', 'name' => 'Owner\'s Draw', 'type' => 'equity', 'sub_type' => 'equity'],
            ['account_code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'sub_type' => 'retained_earnings'],

            // Revenue (4000-4999)
            ['account_code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income', 'sub_type' => 'income'],
            ['account_code' => '4100', 'name' => 'Service Revenue', 'type' => 'income', 'sub_type' => 'income'],
            ['account_code' => '4200', 'name' => 'Other Income', 'type' => 'income', 'sub_type' => 'other_income'],
            ['account_code' => '4300', 'name' => 'Interest Income', 'type' => 'income', 'sub_type' => 'other_income'],
            ['account_code' => '4400', 'name' => 'Discount Received', 'type' => 'income', 'sub_type' => 'other_income'],

            // Cost of Goods Sold (5000-5999)
            ['account_code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5100', 'name' => 'Purchases', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5200', 'name' => 'Purchase Returns', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],
            ['account_code' => '5300', 'name' => 'Freight In', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold'],

            // Expenses (6000-6999)
            ['account_code' => '6000', 'name' => 'Salaries & Wages', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6010', 'name' => 'Employee Benefits', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6020', 'name' => 'Payroll Taxes', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6030', 'name' => 'Allowances Expense', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6040', 'name' => 'Overtime Expense', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6050', 'name' => 'Employer Pension Contributions', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6060', 'name' => 'Employer Health Insurance', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6070', 'name' => 'Workers Compensation', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6100', 'name' => 'Rent Expense', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6200', 'name' => 'Utilities', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6300', 'name' => 'Office Supplies', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6400', 'name' => 'Insurance', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6500', 'name' => 'Professional Fees', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6600', 'name' => 'Advertising & Marketing', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6700', 'name' => 'Travel & Entertainment', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6800', 'name' => 'Depreciation Expense', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6900', 'name' => 'Bank Charges', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6950', 'name' => 'Interest Expense', 'type' => 'expense', 'sub_type' => 'expense'],
            ['account_code' => '6990', 'name' => 'Miscellaneous Expense', 'type' => 'expense', 'sub_type' => 'expense'],
        ];
    }

    /**
     * Create default chart of accounts for a tenant
     *
     * @return int Number of accounts created
     */
    public static function createDefaultAccounts(int $tenantId): int
    {
        $accounts = self::getDefaultAccounts();
        $createdCount = 0;

        foreach ($accounts as $account) {
            $created = ChartOfAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'account_code' => $account['account_code']],
                array_merge($account, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'is_system' => true,
                ])
            );

            if ($created->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Create default chart of accounts for a tenant instance
     *
     * @return int Number of accounts created
     */
    public static function createDefaultAccountsForTenant(Tenant $tenant): int
    {
        return self::createDefaultAccounts($tenant->id);
    }

    /**
     * Check if a tenant has any chart of accounts
     */
    public static function tenantHasAccounts(int $tenantId): bool
    {
        return ChartOfAccount::where('tenant_id', $tenantId)->exists();
    }

    /**
     * Seed default accounts for all tenants that don't have any accounts
     *
     * @return array Summary of seeding operation
     */
    public static function seedAllTenants(): array
    {
        $tenants = Tenant::all();
        $summary = [
            'total_tenants' => $tenants->count(),
            'tenants_seeded' => 0,
            'tenants_skipped' => 0,
            'total_accounts_created' => 0,
        ];

        foreach ($tenants as $tenant) {
            if (! self::tenantHasAccounts($tenant->id)) {
                $accountsCreated = self::createDefaultAccounts($tenant->id);
                $summary['tenants_seeded']++;
                $summary['total_accounts_created'] += $accountsCreated;
            } else {
                // Still add missing accounts for tenants that have some accounts
                $accountsCreated = self::createDefaultAccounts($tenant->id);
                if ($accountsCreated > 0) {
                    $summary['tenants_seeded']++;
                    $summary['total_accounts_created'] += $accountsCreated;
                } else {
                    $summary['tenants_skipped']++;
                }
            }
        }

        return $summary;
    }
}
