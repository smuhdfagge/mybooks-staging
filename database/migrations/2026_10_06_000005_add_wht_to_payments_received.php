<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax a customer deducts when paying us. `amount` stays the
 * money received in the bank; wht_amount is the WHT the customer kept and
 * paid to the tax authority for us, held as a WHT credit note receivable.
 * Net plus WHT settles the invoice.
 *
 * The credit note's number and date are recorded when it arrives; using
 * credits against income tax is a wht_credit_utilisations row with its
 * journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wht_credit_utilisations')) {
            Schema::create('wht_credit_utilisations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->date('utilisation_date');
                $table->decimal('amount', 15, 2);
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('payments_received', 'wht_amount')) {
            Schema::table('payments_received', function (Blueprint $table) {
                $table->foreignId('wht_category_id')->nullable()->constrained('wht_categories')->nullOnDelete();
                $table->decimal('wht_rate', 5, 2)->default(0);
                $table->decimal('wht_base', 15, 2)->default(0);
                $table->decimal('wht_amount', 15, 2)->default(0);
                $table->string('wht_authority', 20)->nullable();
                $table->string('wht_state', 100)->nullable();
                $table->string('wht_credit_note_number', 100)->nullable();
                $table->date('wht_credit_note_date')->nullable();
                $table->foreignId('wht_utilisation_id')->nullable()->constrained('wht_credit_utilisations')->nullOnDelete();
                $table->index(['tenant_id', 'payment_date'], 'payments_received_wht_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments_received', 'wht_amount')) {
            Schema::table('payments_received', function (Blueprint $table) {
                $table->dropIndex('payments_received_wht_index');
                $table->dropConstrainedForeignId('wht_utilisation_id');
                $table->dropConstrainedForeignId('wht_category_id');
                $table->dropColumn(['wht_rate', 'wht_base', 'wht_amount', 'wht_authority', 'wht_state', 'wht_credit_note_number', 'wht_credit_note_date']);
            });
        }

        Schema::dropIfExists('wht_credit_utilisations');
    }
};
