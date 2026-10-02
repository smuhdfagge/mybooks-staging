<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment of one statutory schedule group for a pay month, e.g. PAYE to
 * Kano IRS for September 2026. The journal (Dr liability, Cr bank) is
 * linked; part payments are separate rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('statutory_remittances')) {
            return;
        }

        Schema::create('statutory_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('schedule', 20);
            $table->date('period');
            $table->string('group_key', 30);
            $table->string('group_label');
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'schedule', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_remittances');
    }
};
