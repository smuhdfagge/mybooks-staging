<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank lines from the ledger (audit MB-08): a bank without a live feed gets
 * one line to tick per posted entry on its ledger account. journal_entry_id
 * says which entry, once; journal_id lets a ticked line follow its journal
 * when the journal is re-posted with new entries.
 *
 * No foreign key: a re-posted journal deletes its entries, and a ticked line
 * must outlive that until it is moved to the new entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bank_transactions', 'journal_entry_id')) {
            return;
        }
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('journal_entry_id')->nullable()->unique();
            $table->unsignedBigInteger('journal_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bank_transactions', 'journal_entry_id')) {
            return;
        }
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropUnique(['journal_entry_id']);
            $table->dropIndex(['journal_id']);
            $table->dropColumn(['journal_entry_id', 'journal_id']);
        });
    }
};
