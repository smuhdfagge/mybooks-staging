<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-reversing journals (accruals). A manual journal can carry a
 * "reverse on" date; on that date the mirror-image journal is posted for
 * it and auto_reversal_journal_id points to it (so it is posted once).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journals', 'reverse_on')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->date('reverse_on')->nullable()->after('journal_date');
                $table->unsignedBigInteger('auto_reversal_journal_id')->nullable()->after('reverse_on');
                // The daily command looks for journals due and not yet reversed.
                $table->index(['reverse_on', 'auto_reversal_journal_id'], 'journals_reverse_on_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journals', 'reverse_on')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropIndex('journals_reverse_on_index');
                $table->dropColumn(['reverse_on', 'auto_reversal_journal_id']);
            });
        }
    }
};
