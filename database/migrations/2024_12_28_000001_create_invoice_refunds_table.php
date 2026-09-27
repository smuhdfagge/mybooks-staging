<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('refund_number')->unique();
            $table->date('refund_date');
            $table->decimal('amount', 15, 2);
            $table->string('refund_method'); // cash, bank_transfer, check, credit_card, store_credit, other
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'refund_date']);
            $table->index(['invoice_id', 'status']);
        });

        // Add total_refunded column to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_refunded', 15, 2)->default(0)->after('balance_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('total_refunded');
        });
        
        Schema::dropIfExists('invoice_refunds');
    }
};
