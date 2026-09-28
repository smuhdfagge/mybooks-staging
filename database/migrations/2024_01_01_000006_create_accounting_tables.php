<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('account_code')->nullable();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->string('sub_type')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'account_code']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('journal_number')->unique();
            $table->date('journal_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'posted', 'reversed'])->default('draft');
            $table->boolean('is_posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'journal_number']);
            $table->index(['tenant_id', 'journal_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['journal_id', 'account_id']);
        });

        // Update foreign keys for account references in other tables
        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('recurrent_bill_items', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('recurrent_expenses', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurrent_expenses', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::table('recurrent_bill_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('chart_of_accounts');
    }
};
