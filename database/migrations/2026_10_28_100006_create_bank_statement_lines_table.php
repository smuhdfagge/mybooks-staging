<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lines of a bank statement imported from CSV (audit MB-08). Each is matched
 * to at most one line from the books (bank_transactions), which the
 * reconcile screen then ticks for you. line_hash makes a second upload of the
 * same statement add nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_statement_lines')) {
            return;
        }
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('line_hash', 64);
            $table->date('date');
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 15, 2); // money in positive, money out negative
            $table->decimal('balance', 15, 2)->nullable();
            $table->foreignId('bank_transaction_id')->nullable()->unique()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_id', 'line_hash']);
            $table->index(['bank_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
