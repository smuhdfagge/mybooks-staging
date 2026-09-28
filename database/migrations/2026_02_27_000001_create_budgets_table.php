<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('fiscal_year', 4);
                $table->enum('status', ['draft', 'active', 'locked'])->default('draft');
                $table->text('description')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'fiscal_year', 'name']);
                $table->index(['tenant_id', 'fiscal_year']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('budget_lines')) {
            Schema::create('budget_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts');
                $table->decimal('jan', 15, 2)->default(0);
                $table->decimal('feb', 15, 2)->default(0);
                $table->decimal('mar', 15, 2)->default(0);
                $table->decimal('apr', 15, 2)->default(0);
                $table->decimal('may', 15, 2)->default(0);
                $table->decimal('jun', 15, 2)->default(0);
                $table->decimal('jul', 15, 2)->default(0);
                $table->decimal('aug', 15, 2)->default(0);
                $table->decimal('sep', 15, 2)->default(0);
                $table->decimal('oct', 15, 2)->default(0);
                $table->decimal('nov', 15, 2)->default(0);
                $table->decimal('dec', 15, 2)->default(0);
                $table->decimal('annual_total', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['budget_id', 'account_id']);
                $table->index('account_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
