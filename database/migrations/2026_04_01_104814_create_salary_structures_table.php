<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('salary_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['allowance', 'deduction']);
            $table->string('name');
            $table->enum('amount_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add new payroll-related chart of accounts
        Schema::table('payrolls', function (Blueprint $table) {
            $table->json('allowance_details')->nullable()->after('allowances');
            $table->json('deduction_details')->nullable()->after('other_deductions');
            $table->foreignId('salary_structure_id')->nullable()->after('employee_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['salary_structure_id']);
            $table->dropColumn(['allowance_details', 'deduction_details', 'salary_structure_id']);
        });
        Schema::dropIfExists('salary_structure_items');
        Schema::dropIfExists('salary_structures');
    }
};
