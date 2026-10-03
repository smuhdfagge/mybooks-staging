<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepaid expense and deferred revenue schedules (S9).
 *
 * - accrual_schedules: an amount already sitting in a prepaid (asset) or
 *   deferred revenue (liability) account, moved to the expense or income
 *   account one month at a time.
 * - accrual_schedule_releases: one row per month released, with its journal.
 *   Unique per schedule and month, so a month is only released once.
 * - The default accounts the schedules use, for every existing business
 *   (new ones get them from ChartOfAccountService): Prepaid Expenses (1400)
 *   and Deferred Revenue (2440).
 */
return new class extends Migration
{
    private array $accounts = [
        ['account_code' => '1400', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
        ['account_code' => '2440', 'name' => 'Deferred Revenue', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accrual_schedules')) {
            Schema::create('accrual_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('schedule_number', 50);
                // prepaid_expense: released to an expense; deferred_revenue: released to income.
                $table->string('type', 30);
                $table->string('description');
                $table->decimal('total_amount', 15, 2);
                $table->date('start_date'); // first day of the first month covered
                $table->unsignedSmallInteger('months');
                // Holds what is not yet released: the prepaid asset or the deferred revenue liability.
                $table->foreignId('balance_account_id')->constrained('chart_of_accounts');
                // Where each month goes: the expense or the income account.
                $table->foreignId('pl_account_id')->constrained('chart_of_accounts');
                // Optional link to the bill, expense or invoice that paid or billed it, and free text.
                $table->string('source_type', 20)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('active');
                $table->decimal('released_amount', 15, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'schedule_number']);
                $table->index(['status', 'start_date']);
            });
        }

        if (! Schema::hasTable('accrual_schedule_releases')) {
            Schema::create('accrual_schedule_releases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('accrual_schedule_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('sequence'); // month 1, 2, ...
                $table->date('due_date');    // last day of that month
                $table->date('posted_date'); // later when that month's period was closed
                $table->decimal('amount', 15, 2);
                $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
                $table->string('note')->nullable();
                $table->timestamps();

                $table->unique(['accrual_schedule_id', 'sequence']);
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
        Schema::dropIfExists('accrual_schedule_releases');
        Schema::dropIfExists('accrual_schedules');
        // The accounts are left in place: they may hold postings.
    }
};
