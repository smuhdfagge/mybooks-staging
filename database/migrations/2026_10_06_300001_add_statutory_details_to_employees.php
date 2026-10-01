<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory details for Nigerian payroll remittances:
 * - the state whose IRS receives the employee's PAYE (state of residence),
 * - the employee's Pension Fund Administrator and RSA PIN,
 * - the employee's NHF number.
 *
 * PFAs come from a list: rows with no tenant are the PenCom list shared by
 * every business; a business can add its own. Nigeria and its states are
 * added if the StateSeeder was never run, so the state list is never empty.
 */
return new class extends Migration
{
    /**
     * Licensed PFAs as listed by PenCom
     * (https://www.pencom.gov.ng/pension-fund-administrators/, read October 2026).
     */
    public const PFAS = [
        'Access Pensions Limited',
        'CardinalStone Pensions Limited',
        'Citizens Pensions Limited',
        'CrusaderSterling Pensions Limited',
        'FCMB Pensions Limited',
        'Fidelity Pension Managers Limited',
        'Guaranty Trust Pension Managers Limited',
        'Leadway Pensure PFA Limited',
        'Nigerian University Pension Management Company (NUPEMCO)',
        'NLPC Pension Fund Administrators Limited',
        'Norrenberger Pensions Limited',
        'NPF Pensions Limited',
        'OAK Pensions Limited',
        'Parthian Pensions Limited',
        'Premium Pension Limited',
        'Stanbic IBTC Pension Managers Limited',
        'Tangerine APT Pensions Limited',
        'Trustfund Pensions Limited',
        'Veritas Glanvills Pensions Limited',
    ];

    private const STATES = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River',
        'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT Abuja', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano',
        'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo',
        'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pension_fund_administrators')) {
            Schema::create('pension_fund_administrators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('source')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        foreach (self::PFAS as $name) {
            $exists = DB::table('pension_fund_administrators')->whereNull('tenant_id')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('pension_fund_administrators')->insert([
                    'tenant_id' => null,
                    'name' => $name,
                    'is_active' => true,
                    'source' => 'PenCom licensed PFAs (pencom.gov.ng), October 2026',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'tax_state_id')) {
                $table->foreignId('tax_state_id')->nullable()->after('annual_rent')->constrained('states')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'pension_fund_administrator_id')) {
                $table->foreignId('pension_fund_administrator_id')->nullable()->after('tax_state_id')
                    ->constrained('pension_fund_administrators')->nullOnDelete();
            }
            // Encrypted like tax_id, so text columns.
            if (! Schema::hasColumn('employees', 'rsa_pin')) {
                $table->text('rsa_pin')->nullable()->after('pension_fund_administrator_id');
            }
            if (! Schema::hasColumn('employees', 'nhf_number')) {
                $table->text('nhf_number')->nullable()->after('rsa_pin');
            }
        });

        $this->ensureNigerianStates();
    }

    private function ensureNigerianStates(): void
    {
        if (! Schema::hasTable('countries') || ! Schema::hasTable('states')) {
            return;
        }

        $countryId = DB::table('countries')->where('code', 'NG')->value('id');
        if (! $countryId) {
            $countryId = DB::table('countries')->insertGetId([
                'name' => 'Nigeria', 'code' => 'NG', 'phone_code' => '+234', 'currency' => 'NGN', 'currency_symbol' => '₦',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::STATES as $state) {
            if (! DB::table('states')->where('country_id', $countryId)->where('name', $state)->exists()) {
                DB::table('states')->insert(['name' => $state, 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'pension_fund_administrator_id')) {
                $table->dropConstrainedForeignId('pension_fund_administrator_id');
            }
            if (Schema::hasColumn('employees', 'tax_state_id')) {
                $table->dropConstrainedForeignId('tax_state_id');
            }
            $table->dropColumn(['rsa_pin', 'nhf_number']);
        });
        Schema::dropIfExists('pension_fund_administrators');
    }
};
