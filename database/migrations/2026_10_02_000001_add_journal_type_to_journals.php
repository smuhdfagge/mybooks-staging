<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journals get a type so the year-end closing journals can be left out of
 * the profit and loss (finding A8). Existing closing journals are found by
 * the references the close has always used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journals', 'journal_type')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->string('journal_type', 30)->nullable()->after('reference_id');
                $table->index(['tenant_id', 'journal_type']);
            });
        }

        DB::table('journals')
            ->where('reference', 'like', 'year-end-close%')
            ->update(['journal_type' => 'closing']);
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'journal_type']);
            $table->dropColumn('journal_type');
        });
    }
};
