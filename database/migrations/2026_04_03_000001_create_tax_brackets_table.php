<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "2026 Income Tax Slabs"
            $table->decimal('min_amount', 15, 2); // lower bound of bracket
            $table->decimal('max_amount', 15, 2)->nullable(); // null = no upper limit
            $table->decimal('rate', 5, 2); // percentage rate for this bracket
            $table->decimal('fixed_amount', 15, 2)->default(0); // flat tax on amounts below this bracket
            $table->enum('period', ['monthly', 'annual'])->default('annual');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        // Add employer_contributions and employer_contribution_details to payrolls
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('employer_contributions', 15, 2)->default(0)->after('deduction_details');
            $table->json('employer_contribution_details')->nullable()->after('employer_contributions');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['employer_contributions', 'employer_contribution_details']);
        });

        Schema::dropIfExists('tax_brackets');
    }
};
