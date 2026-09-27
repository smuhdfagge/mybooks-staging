<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number')->unique();
            $table->string('type');                        // loan, advance, salary_advance
            $table->string('description')->nullable();
            $table->decimal('principal_amount', 15, 2);    // original loan amount
            $table->decimal('interest_rate', 5, 2)->default(0); // annual interest rate %
            $table->unsignedInteger('total_installments');  // total number of installments
            $table->decimal('installment_amount', 15, 2);  // fixed monthly deduction
            $table->unsignedInteger('installments_paid')->default(0);
            $table->decimal('amount_repaid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2); // remaining balance
            $table->date('disbursement_date');              // when loan was given
            $table->date('first_deduction_date');           // when deductions start
            $table->date('last_deduction_date')->nullable(); // calculated end date
            $table->enum('status', ['active', 'completed', 'cancelled', 'paused'])->default('active');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('employee_loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_portion', 15, 2);
            $table->decimal('interest_portion', 15, 2)->default(0);
            $table->decimal('remaining_balance', 15, 2);
            $table->date('deduction_date');
            $table->timestamps();

            $table->index(['employee_loan_id', 'installment_number'], 'loan_repayments_loan_installment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_repayments');
        Schema::dropIfExists('employee_loans');
    }
};
