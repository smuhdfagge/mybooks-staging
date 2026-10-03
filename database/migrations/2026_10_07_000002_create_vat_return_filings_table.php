<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A monthly VAT return marked as filed: the figures entered by hand (lines
 * 65, 85, 90), the credit brought forward (line 100), the lines as filed
 * and the settlement journal. Line 115 of one filing is line 100 of the
 * next. See App\Actions\VatReturns\FileVatReturn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vat_return_filings')) {
            return;
        }

        Schema::create('vat_return_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->decimal('imports', 15, 2)->default(0);
            $table->decimal('import_vat', 15, 2)->default(0);
            $table->decimal('vat_withheld', 15, 2)->default(0);
            $table->decimal('auto_vat_paid', 15, 2)->default(0);
            $table->decimal('credit_brought_forward', 15, 2)->default(0);
            $table->decimal('output_vat', 15, 2)->default(0);
            $table->decimal('input_vat', 15, 2)->default(0);
            $table->decimal('vat_payable', 15, 2)->default(0);
            $table->decimal('credit_carried_forward', 15, 2)->default(0);
            $table->json('lines');
            $table->foreignId('settlement_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('reference', 100)->nullable(); // NRS acknowledgement or receipt number
            $table->timestamp('filed_at');
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_return_filings');
    }
};
