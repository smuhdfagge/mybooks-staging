<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small businesses getting started',
                'monthly_price' => 25000.00,
                'annual_price' => 250000.00, // ~17% discount (2 months free)
                'allow_monthly_billing' => true,
                'allow_annual_billing' => true,
                'max_users' => 5,
                'features' => [
                    'Up to 5 users',
                    'Invoicing & Billing',
                    'Expense Tracking',
                    'Financial Reports',
                    'Customer Management',
                    'Vendor Management',
                    'Inventory Management',
                    'Chart of Accounts',
                    'Bank Reconciliation',
                    'Email Support',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing businesses that need more power',
                'monthly_price' => 50000.00,
                'annual_price' => 500000.00, // ~17% discount (2 months free)
                'allow_monthly_billing' => false, // Only annual billing
                'allow_annual_billing' => true,
                'max_users' => 15,
                'features' => [
                    'Up to 15 users',
                    'All Starter features',
                    'HR & Payroll Management',
                    'Advanced Reporting & Analytics',
                    'Multi-currency Support',
                    'Recurring Invoices',
                    'Sales Orders',
                    'Purchase Orders',
                    'Tax Management',
                    'Custom Report Builder',
                    'Priority Support',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with advanced needs',
                'monthly_price' => 150000.00,
                'annual_price' => 1500000.00, // ~17% discount (2 months free)
                'allow_monthly_billing' => true,
                'allow_annual_billing' => true,
                'max_users' => 50,
                'features' => [
                    'Up to 50 users',
                    'All Professional features',
                    'Multi-branch Support',
                    'Custom Integrations',
                    'API Access',
                    'Dedicated Account Manager',
                    'Custom Training Sessions',
                    'SLA Guarantee',
                    '24/7 Phone Support',
                    'Data Export & Backup',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
