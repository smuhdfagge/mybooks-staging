<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credit notes:
 *  - restock: the goods came back, so opening the note puts them back into
 *    stock at cost (finding A15: returned goods never went back to stock);
 *  - unit_cost on each line: the cost the goods went back in at, so voiding
 *    takes out exactly what was put back;
 *  - credit_note_refunds: money paid back to the customer out of the credit.
 * Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('credit_notes', 'restock')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->boolean('restock')->default(false)->after('reason');
            });
        }

        if (! Schema::hasColumn('credit_note_items', 'unit_cost')) {
            Schema::table('credit_note_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 15, 4)->nullable()->after('unit_price');
            });
        }

        if (! Schema::hasTable('credit_note_refunds')) {
            Schema::create('credit_note_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
                $table->date('refund_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 50);
                $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'refund_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_refunds');
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn('restock');
        });
    }
};
