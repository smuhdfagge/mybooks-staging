<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Finding A3 (round 3).
 *
 * 1. Add the Nigeria PAYE template for 2026 onwards (Nigeria Tax Act 2025,
 *    in force 1 January 2026) and stop offering the 2024 table as current.
 *    Businesses choose when to apply it on the Tax Templates page.
 * 2. Pension, NHF and health insurance deductions are reliefs: they come off
 *    pay before PAYE is worked out. Mark existing ones as pre-tax, so payroll
 *    treats them that way (the flag existed but payroll never used it).
 */
return new class extends Migration
{
    private const RELIEF_NAMES = ['%pension%', '%nhf%', '%housing fund%', '%nhis%', '%health insurance%'];

    public function up(): void
    {
        $template = [
            'country_code' => 'NGA',
            'tax_year' => 2026,
            'period' => 'annual',
        ];

        $values = [
            'name' => 'Nigeria PAYE 2026 (Nigeria Tax Act 2025)',
            'is_current' => true,
            'description' => 'Annual bands from the Nigeria Tax Act 2025, in force from 1 January 2026. '
                .'The consolidated relief allowance is abolished. Pension, NHF and health insurance deductions '
                .'marked "pre-tax" are taken off pay before these bands are applied. Confirm with your tax adviser.',
            'brackets' => json_encode([
                ['name' => 'First ₦800,000', 'min' => 0, 'max' => 800000, 'rate' => 0, 'fixed_amount' => 0],
                ['name' => 'Next ₦2,200,000', 'min' => 800000, 'max' => 3000000, 'rate' => 15, 'fixed_amount' => 0],
                ['name' => 'Next ₦9,000,000', 'min' => 3000000, 'max' => 12000000, 'rate' => 18, 'fixed_amount' => 0],
                ['name' => 'Next ₦13,000,000', 'min' => 12000000, 'max' => 25000000, 'rate' => 21, 'fixed_amount' => 0],
                ['name' => 'Next ₦25,000,000', 'min' => 25000000, 'max' => 50000000, 'rate' => 23, 'fixed_amount' => 0],
                ['name' => 'Above ₦50,000,000', 'min' => 50000000, 'max' => null, 'rate' => 25, 'fixed_amount' => 0],
            ]),
            'employer_contributions' => json_encode([
                ['name' => 'Pension (Employer)', 'type' => 'percentage', 'rate' => 10, 'cap' => null],
                ['name' => 'NSITF (Employee Compensation)', 'type' => 'percentage', 'rate' => 1, 'cap' => null],
                ['name' => 'ITF (Industrial Training Fund)', 'type' => 'percentage', 'rate' => 1, 'cap' => null],
            ]),
            'updated_at' => now(),
        ];

        if (DB::table('statutory_tax_templates')->where($template)->exists()) {
            DB::table('statutory_tax_templates')->where($template)->update($values);
        } else {
            DB::table('statutory_tax_templates')->insert($template + $values + ['created_at' => now()]);
        }

        DB::table('statutory_tax_templates')
            ->where('country_code', 'NGA')
            ->where('tax_year', '<', 2026)
            ->update(['is_current' => false]);

        foreach (['salary_structure_items' => fn ($q) => $q->where('type', 'deduction'), 'deductions' => fn ($q) => $q] as $table => $scope) {
            $query = $scope(DB::table($table))->where(function ($q) {
                foreach (self::RELIEF_NAMES as $pattern) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', [$pattern]);
                }
            });
            $query->update(['is_taxable' => true]);
        }
    }

    public function down(): void
    {
        DB::table('statutory_tax_templates')->where(['country_code' => 'NGA', 'tax_year' => 2026])->delete();
        DB::table('statutory_tax_templates')->where(['country_code' => 'NGA', 'tax_year' => 2024])->update(['is_current' => true]);
        // The pre-tax flags are left as they are: they describe the deductions correctly.
    }
};
