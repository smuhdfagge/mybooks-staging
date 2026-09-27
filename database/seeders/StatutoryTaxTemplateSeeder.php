<?php

namespace Database\Seeders;

use App\Models\StatutoryTaxTemplate;
use Illuminate\Database\Seeder;

class StatutoryTaxTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Nigeria PAYE (Personal Income Tax Act) - 2024 rates
            [
                'country_code' => 'NGA',
                'name' => 'Nigeria PAYE 2024',
                'tax_year' => 2024,
                'period' => 'annual',
                'is_current' => true,
                'description' => 'Nigeria Personal Income Tax (PAYE) - Personal Income Tax Act rates. Consolidated relief allowance of ₦200,000 or 1% of gross income (whichever is higher) + 20% of gross income should be applied before these brackets.',
                'brackets' => [
                    ['name' => 'First ₦300,000', 'min' => 0, 'max' => 300000, 'rate' => 7, 'fixed_amount' => 0],
                    ['name' => 'Next ₦300,000', 'min' => 300000, 'max' => 600000, 'rate' => 11, 'fixed_amount' => 0],
                    ['name' => 'Next ₦500,000', 'min' => 600000, 'max' => 1100000, 'rate' => 15, 'fixed_amount' => 0],
                    ['name' => 'Next ₦500,000', 'min' => 1100000, 'max' => 1600000, 'rate' => 19, 'fixed_amount' => 0],
                    ['name' => 'Next ₦1,600,000', 'min' => 1600000, 'max' => 3200000, 'rate' => 21, 'fixed_amount' => 0],
                    ['name' => 'Above ₦3,200,000', 'min' => 3200000, 'max' => null, 'rate' => 24, 'fixed_amount' => 0],
                ],
                'employer_contributions' => [
                    ['name' => 'Pension (Employer)', 'type' => 'percentage', 'rate' => 10, 'cap' => null],
                    ['name' => 'NHF (National Housing Fund)', 'type' => 'percentage', 'rate' => 2.5, 'cap' => null],
                    ['name' => 'NSITF (Employee Compensation)', 'type' => 'percentage', 'rate' => 1, 'cap' => null],
                    ['name' => 'ITF (Industrial Training Fund)', 'type' => 'percentage', 'rate' => 1, 'cap' => null],
                ],
            ],

            // Kenya PAYE - 2024 rates
            [
                'country_code' => 'KEN',
                'name' => 'Kenya PAYE 2024',
                'tax_year' => 2024,
                'period' => 'monthly',
                'is_current' => true,
                'description' => 'Kenya Pay As You Earn (PAYE) monthly tax bands. Personal relief of KES 2,400 per month applies after tax calculation.',
                'brackets' => [
                    ['name' => 'First KES 24,000', 'min' => 0, 'max' => 24000, 'rate' => 10, 'fixed_amount' => 0],
                    ['name' => 'KES 24,001 - 32,333', 'min' => 24000, 'max' => 32333, 'rate' => 25, 'fixed_amount' => 0],
                    ['name' => 'KES 32,334 - 500,000', 'min' => 32333, 'max' => 500000, 'rate' => 30, 'fixed_amount' => 0],
                    ['name' => 'KES 500,001 - 800,000', 'min' => 500000, 'max' => 800000, 'rate' => 32.5, 'fixed_amount' => 0],
                    ['name' => 'Above KES 800,000', 'min' => 800000, 'max' => null, 'rate' => 35, 'fixed_amount' => 0],
                ],
                'employer_contributions' => [
                    ['name' => 'NSSF Employer (Tier I)', 'type' => 'percentage', 'rate' => 6, 'cap' => 1080],
                    ['name' => 'NSSF Employer (Tier II)', 'type' => 'percentage', 'rate' => 6, 'cap' => 1080],
                    ['name' => 'NHIF Employer', 'type' => 'fixed', 'rate' => 1700, 'cap' => null],
                ],
            ],

            // Ghana PAYE - 2024 rates
            [
                'country_code' => 'GHA',
                'name' => 'Ghana PAYE 2024',
                'tax_year' => 2024,
                'period' => 'monthly',
                'is_current' => true,
                'description' => 'Ghana Pay As You Earn (PAYE) monthly tax bands per Ghana Revenue Authority.',
                'brackets' => [
                    ['name' => 'First GHS 490', 'min' => 0, 'max' => 490, 'rate' => 0, 'fixed_amount' => 0],
                    ['name' => 'Next GHS 110', 'min' => 490, 'max' => 600, 'rate' => 5, 'fixed_amount' => 0],
                    ['name' => 'Next GHS 130', 'min' => 600, 'max' => 730, 'rate' => 10, 'fixed_amount' => 0],
                    ['name' => 'Next GHS 3,166.67', 'min' => 730, 'max' => 3896.67, 'rate' => 17.5, 'fixed_amount' => 0],
                    ['name' => 'Next GHS 16,000', 'min' => 3896.67, 'max' => 19896.67, 'rate' => 25, 'fixed_amount' => 0],
                    ['name' => 'Next GHS 29,766.67', 'min' => 19896.67, 'max' => 49663.34, 'rate' => 30, 'fixed_amount' => 0],
                    ['name' => 'Above GHS 49,663.34', 'min' => 49663.34, 'max' => null, 'rate' => 35, 'fixed_amount' => 0],
                ],
                'employer_contributions' => [
                    ['name' => 'SSNIT Employer', 'type' => 'percentage', 'rate' => 13, 'cap' => null],
                ],
            ],

            // South Africa PAYE - 2024/2025 tax year
            [
                'country_code' => 'ZAF',
                'name' => 'South Africa PAYE 2024/25',
                'tax_year' => 2024,
                'period' => 'annual',
                'is_current' => true,
                'description' => 'South Africa income tax brackets for 2024/25 tax year. Primary rebate R17,235, secondary rebate (65+) R9,444, tertiary rebate (75+) R3,145.',
                'brackets' => [
                    ['name' => 'R0 - R237,100', 'min' => 0, 'max' => 237100, 'rate' => 18, 'fixed_amount' => 0],
                    ['name' => 'R237,101 - R370,500', 'min' => 237100, 'max' => 370500, 'rate' => 26, 'fixed_amount' => 0],
                    ['name' => 'R370,501 - R512,800', 'min' => 370500, 'max' => 512800, 'rate' => 31, 'fixed_amount' => 0],
                    ['name' => 'R512,801 - R673,000', 'min' => 512800, 'max' => 673000, 'rate' => 36, 'fixed_amount' => 0],
                    ['name' => 'R673,001 - R857,900', 'min' => 673000, 'max' => 857900, 'rate' => 39, 'fixed_amount' => 0],
                    ['name' => 'R857,901 - R1,817,000', 'min' => 857900, 'max' => 1817000, 'rate' => 41, 'fixed_amount' => 0],
                    ['name' => 'Above R1,817,000', 'min' => 1817000, 'max' => null, 'rate' => 45, 'fixed_amount' => 0],
                ],
                'employer_contributions' => [
                    ['name' => 'UIF Employer', 'type' => 'percentage', 'rate' => 1, 'cap' => 177.12],
                    ['name' => 'SDL (Skills Development Levy)', 'type' => 'percentage', 'rate' => 1, 'cap' => null],
                ],
            ],

            // UK PAYE - 2024/25
            [
                'country_code' => 'GBR',
                'name' => 'UK PAYE 2024/25',
                'tax_year' => 2024,
                'period' => 'annual',
                'is_current' => true,
                'description' => 'UK income tax bands for 2024/25. Personal allowance £12,570 (tapers above £100,000). National Insurance handled separately.',
                'brackets' => [
                    ['name' => 'Personal Allowance', 'min' => 0, 'max' => 12570, 'rate' => 0, 'fixed_amount' => 0],
                    ['name' => 'Basic Rate', 'min' => 12570, 'max' => 50270, 'rate' => 20, 'fixed_amount' => 0],
                    ['name' => 'Higher Rate', 'min' => 50270, 'max' => 125140, 'rate' => 40, 'fixed_amount' => 0],
                    ['name' => 'Additional Rate', 'min' => 125140, 'max' => null, 'rate' => 45, 'fixed_amount' => 0],
                ],
                'employer_contributions' => [
                    ['name' => 'Employer NI (Class 1)', 'type' => 'percentage', 'rate' => 13.8, 'cap' => null],
                    ['name' => 'Workplace Pension (min)', 'type' => 'percentage', 'rate' => 3, 'cap' => null],
                ],
            ],
        ];

        foreach ($templates as $template) {
            StatutoryTaxTemplate::updateOrCreate(
                [
                    'country_code' => $template['country_code'],
                    'tax_year' => $template['tax_year'],
                    'period' => $template['period'],
                ],
                $template
            );
        }
    }
}
