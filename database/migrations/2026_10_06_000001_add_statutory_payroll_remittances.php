<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll statutory remittances (tax pack 1): PAYE goes to the state the
 * employee lives in, pension to their PFA, plus NHF, NSITF and ITF.
 *
 * - employees: state of residence for PAYE, PFA name, RSA PIN, NHF number
 *   and whether they are registered for NHF.
 * - payrolls: a snapshot of those details when the payslip was made.
 * - statutory_remittances: each payment to a tax office or fund.
 * - NHF, NSITF and ITF get their own liability accounts.
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
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'tax_state')) {
                $table->string('tax_state', 100)->nullable();
            }
            if (! Schema::hasColumn('employees', 'pfa_name')) {
                $table->string('pfa_name', 150)->nullable();
            }
            if (! Schema::hasColumn('employees', 'rsa_pin')) {
                $table->text('rsa_pin')->nullable(); // encrypted
            }
            if (! Schema::hasColumn('employees', 'nhf_number')) {
                $table->text('nhf_number')->nullable(); // encrypted
            }
            if (! Schema::hasColumn('employees', 'nhf_registered')) {
                $table->boolean('nhf_registered')->default(false);
            }
        });

        if (! Schema::hasColumn('payrolls', 'statutory')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->json('statutory')->nullable();
            });
        }

        if (! Schema::hasTable('statutory_remittances')) {
            Schema::create('statutory_remittances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('body', 30); // paye, pension, nhf, nsitf, itf, wht, or another account
                $table->string('account_code', 20);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('paid_to', 150)->nullable(); // state IRS, PFA, ...
                $table->decimal('amount', 15, 2);
                $table->date('paid_on');
                $table->string('payment_method', 30)->nullable();
                $table->string('reference', 100)->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'body', 'period_start']);
            });
        }

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
        Schema::dropIfExists('statutory_remittances');
        if (Schema::hasColumn('payrolls', 'statutory')) {
            Schema::table('payrolls', fn (Blueprint $table) => $table->dropColumn('statutory'));
        }
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tax_state', 'pfa_name', 'rsa_pin', 'nhf_number', 'nhf_registered']);
        });
        // Accounts are left in place: they may hold postings.
    }
};
