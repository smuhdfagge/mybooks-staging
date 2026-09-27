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
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('data_source'); // invoices, bills, expenses, customers, vendors, items, payments_received, payments_made, payroll
            $table->json('columns'); // Selected columns to display
            $table->json('filters')->nullable(); // Filter conditions
            $table->string('group_by')->nullable(); // Group by field
            $table->json('sort_by')->nullable(); // Sort configuration
            $table->json('aggregations')->nullable(); // SUM, COUNT, AVG calculations
            $table->string('date_field')->nullable(); // Date field for date range filtering
            $table->boolean('is_public')->default(false); // Share with team
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_reports');
    }
};
