<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Resolves account codes per-tenant, falling back to system defaults.
 *
 * Tenants can override any account code via their JSON settings column:
 *   $tenant->settings['account_mappings']['accounts_receivable'] = '1210';
 *
 * All JournalService references go through this service so that a tenant
 * who customizes their chart of accounts is not broken by hardcoded codes.
 */
class AccountCodeService
{
    /**
     * System defaults — the standard chart-of-accounts codes shipped with MyBooks.
     * Keys are logical names; values are the default account codes.
     */
    protected static array $defaults = [
        // Assets
        'cash' => '1000',
        'checking' => '1100',
        'accounts_receivable' => '1200',
        'employee_advances' => '1250',
        'inventory' => '1300',
        'prepaid_expenses' => '1400',
        'fixed_assets' => '1500',
        'accumulated_depreciation' => '1600',
        'input_vat' => '1410',
        'supplier_advances' => '1420', // money paid to a supplier before their bill
        'wht_receivable' => '1430', // WHT credit notes receivable (customers withheld from us)

        // Liabilities
        'accounts_payable' => '2000',
        'credit_card_payable' => '2100',
        'accrued_salaries' => '2210',
        'payroll_liabilities' => '2300',
        'tax_payable' => '2310',
        'pension_payable' => '2320',
        'insurance_payable' => '2330',
        'union_dues_payable' => '2340',
        'customer_deposits' => '2350',
        'garnishments_payable' => '2360',
        'nhf_payable' => '2370',
        'nsitf_payable' => '2380',
        'itf_payable' => '2390',
        'wht_payable' => '2420', // WHT we withheld from vendors, owed to the tax authority
        'sales_tax_payable' => '2400', // output VAT
        'vat_payable' => '2410', // net VAT owed after a return is settled
        'income_tax_payable' => '2430', // company income tax; WHT credits are used against it
        'deferred_revenue' => '2440', // income received before it is earned (S9 schedules)

        // Equity
        'owners_capital' => '3000',
        'retained_earnings' => '3200',
        'income_summary' => '3300',

        // Income
        'sales_revenue' => '4000',
        'interest_income' => '4300',
        'other_income' => '4200',

        // COGS
        'cost_of_goods_sold' => '5000',
        'stock_losses' => '5400', // goods lost on a stock transfer (session 13)
        'production_costs_applied' => '5500', // assembly extra costs moved into stock (session 14)

        // Expenses
        'salaries_wages' => '6000',
        'payroll_taxes' => '6020',
        'allowances_expense' => '6030',
        'overtime_expense' => '6040',
        'employer_pension' => '6050',
        'employer_health_insurance' => '6060',
        'workers_comp' => '6070',
        'depreciation_expense' => '6800',
        'miscellaneous_expense' => '6990',
    ];

    /**
     * Payment-method → logical account name mapping.
     */
    protected static array $paymentMethodMap = [
        'cash' => 'cash',
        'check' => 'checking',
        'cheque' => 'checking',
        'bank_transfer' => 'checking',
        'credit_card' => 'credit_card_payable',
        'debit_card' => 'checking',
        'online' => 'checking',
        'mobile_money' => 'cash',
        'deposit' => 'customer_deposits',
        'other' => 'cash',
    ];

    /**
     * Resolve a single account code for the given tenant.
     */
    public static function resolve(int $tenantId, string $logicalName): string
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant) {
            $mappings = $tenant->settings['account_mappings'] ?? [];
            if (isset($mappings[$logicalName]) && $mappings[$logicalName] !== '') {
                return $mappings[$logicalName];
            }
        }

        return static::$defaults[$logicalName] ?? throw new \InvalidArgumentException(
            "Unknown account logical name: {$logicalName}"
        );
    }

    /**
     * Resolve the account code for a payment method.
     */
    public static function resolvePaymentMethod(int $tenantId, ?string $paymentMethod): string
    {
        if ($paymentMethod === null || $paymentMethod === '') {
            return static::resolve($tenantId, 'cash');
        }

        $method = strtolower(str_replace(' ', '_', $paymentMethod));
        $logicalName = static::$paymentMethodMap[$method] ?? 'cash';

        return static::resolve($tenantId, $logicalName);
    }

    /**
     * Get all defaults (used for documentation / settings UI).
     */
    public static function getDefaults(): array
    {
        return static::$defaults;
    }

    /**
     * Get current mappings for a tenant (merged with defaults).
     */
    public static function getMappings(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        $overrides = $tenant?->settings['account_mappings'] ?? [];

        return array_merge(static::$defaults, $overrides);
    }
}
