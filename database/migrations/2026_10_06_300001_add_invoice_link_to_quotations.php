<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quotation can be turned straight into an invoice (not only a sales
 * order), so it keeps a link to that invoice, and remembers when it was
 * emailed. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotations', 'converted_to_invoice_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->foreignId('converted_to_invoice_id')->nullable()->after('converted_to_so_id')
                    ->constrained('invoices')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('quotations', 'sent_at')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_to_invoice_id');
            $table->dropColumn('sent_at');
        });
    }
};
