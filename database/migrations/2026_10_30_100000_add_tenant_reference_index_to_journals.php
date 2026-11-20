<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finding a document's journal by business first (audit MB-30). Posting
 * looks it up with a locking read; on a first posting that locks the gap
 * in the index where the journal will go. Starting the index with the
 * business keeps each business's gaps its own (but for the one where one
 * business's journals end and the next one's begin), so two businesses
 * posting new documents at once don't wait on (or deadlock with) each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('journals', 'journals_tenant_reference_index')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->index(['tenant_id', 'reference_type', 'reference_id'], 'journals_tenant_reference_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('journals', 'journals_tenant_reference_index')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropIndex('journals_tenant_reference_index');
            });
        }
    }
};
