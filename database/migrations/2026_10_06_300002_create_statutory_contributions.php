<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory payroll contributions (Nigeria): each business's rates, bases
 * and due dates for PAYE, pension, NHF, NSITF and ITF. The rows are created
 * from StatutoryContribution::DEFAULTS the first time a business needs them,
 * so the defaults and their sources live in one place.
 *
 * Payroll records keep the employee's PAYE state, PFA and taxable pay as at
 * the pay run, so later changes to the employee don't move old schedules.
 *
 * NHF, NSITF and ITF get their own liability accounts (2370, 2380, 2390);
 * before, NHF, NSITF and ITF all went to Payroll Liabilities (2300).
 */
return new class extends Migration
{
    private array $accounts = [
        ['account_code' => '2370', 'name' => 'NHF Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
        ['account_code' => '2380', 'name' => 'NSITF Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
        ['account_code' => '2390', 'name' => 'ITF Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('statutory_contributions')) {
            Schema::create('statutory_contributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name');
                $table->decimal('rate', 8, 4)->nullable();
                $table->string('base', 20)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->string('due_rule', 30);
                $table->unsignedSmallInteger('due_value')->nullable();
                $table->date('effective_from')->nullable();
                $table->text('source')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'tax_state_id')) {
                $table->foreignId('tax_state_id')->nullable()->after('employee_id')->constrained('states')->nullOnDelete();
            }
            if (! Schema::hasColumn('payrolls', 'pension_fund_administrator_id')) {
                $table->foreignId('pension_fund_administrator_id')->nullable()->after('tax_state_id')
                    ->constrained('pension_fund_administrators')->nullOnDelete();
            }
            if (! Schema::hasColumn('payrolls', 'taxable_income')) {
                $table->decimal('taxable_income', 15, 2)->nullable()->after('gross_salary');
            }
        });

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
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'pension_fund_administrator_id')) {
                $table->dropConstrainedForeignId('pension_fund_administrator_id');
            }
            if (Schema::hasColumn('payrolls', 'tax_state_id')) {
                $table->dropConstrainedForeignId('tax_state_id');
            }
            if (Schema::hasColumn('payrolls', 'taxable_income')) {
                $table->dropColumn('taxable_income');
            }
        });
        Schema::dropIfExists('statutory_contributions');
        // The accounts are left in place: they may hold postings.
    }
};
