<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A prepaid or deferred schedule linked to a bill, expense or invoice now
 * posts a setup journal that moves the amount from the account that
 * document used into Prepaid Expenses or Deferred Revenue (audit MB-53).
 * Before, nothing moved it, so each released month was counted a second
 * time. This links the schedule to that journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('accrual_schedules', 'setup_journal_id')) {
            return;
        }
        Schema::table('accrual_schedules', function (Blueprint $table) {
            $table->foreignId('setup_journal_id')->nullable()->after('source_id')
                ->constrained('journals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('accrual_schedules', 'setup_journal_id')) {
            return;
        }
        Schema::table('accrual_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setup_journal_id');
        });
    }
};
