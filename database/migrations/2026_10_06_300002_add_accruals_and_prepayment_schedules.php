<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accruals and prepayments.
 *
 * - journals.reverse_on: a manual journal (e.g. an accrual at month end)
 *   that is reversed automatically on that date; auto_reversal_journal_id
 *   is the reversal once posted (so it is only ever posted once).
 * - accrual_schedules / accrual_schedule_releases: a prepaid expense or
 *   income received in advance, released to the profit and loss one month
 *   at a time.
 * - A "Deferred Revenue" liability account (2380) for every business.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journals', 'reverse_on')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->date('reverse_on')->nullable()->after('journal_date');
                $table->unsignedBigInteger('auto_reversal_journal_id')->nullable()->after('reverse_on');
                $table->index(['reverse_on', 'auto_reversal_journal_id']);
            });
        }

        if (! Schema::hasTable('accrual_schedules')) {
            Schema::create('accrual_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('schedule_number', 50);
                // prepaid_expense: paid in advance, released to an expense.
                // deferred_revenue: received in advance, released to income.
                $table->string('type', 30);
                $table->string('description');
                $table->decimal('total_amount', 15, 2);
                $table->date('recorded_date'); // when it was paid or received
                $table->date('start_date');    // first month it covers
                $table->unsignedSmallInteger('months');
                // The prepaid asset or deferred revenue liability that holds what is left.
                $table->foreignId('balance_account_id')->constrained('chart_of_accounts');
                // The expense or income account each month goes to.
                $table->foreignId('pl_account_id')->constrained('chart_of_accounts');
                // How the amount got into the balance account: bank (paid/received now),
                // reclassify (moved from the expense/income account), existing (already there).
                $table->string('funding', 20)->default('bank');
                $table->foreignId('funding_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('status', 20)->default('active');
                $table->decimal('released_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
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
                $table->date('due_date');
                $table->date('posted_date');
                $table->decimal('amount', 15, 2);
                $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
                $table->string('note')->nullable();
                $table->timestamps();

                // A month can only be released once (the command is safe to run again).
                $table->unique(['accrual_schedule_id', 'sequence']);
            });
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $exists = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->where('account_code', '2380')->exists();
            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'tenant_id' => $tenantId,
                    'account_code' => '2380',
                    'name' => 'Deferred Revenue',
                    'type' => 'liability',
                    'sub_type' => 'other_current_liability',
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

    public function down(): void
    {
        Schema::dropIfExists('accrual_schedule_releases');
        Schema::dropIfExists('accrual_schedules');
        if (Schema::hasColumn('journals', 'reverse_on')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropIndex(['reverse_on', 'auto_reversal_journal_id']);
                $table->dropColumn(['reverse_on', 'auto_reversal_journal_id']);
            });
        }
        // The 2380 account is left in place: it may hold postings.
    }
};
