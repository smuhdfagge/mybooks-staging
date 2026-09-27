<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add version tracking to salary_structures
        Schema::table('salary_structures', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('name');
        });

        // Add salary_structure_snapshot to payrolls
        Schema::table('payrolls', function (Blueprint $table) {
            $table->json('salary_structure_snapshot')->nullable()->after('salary_structure_id');
        });

        // Immutable version history for salary structures
        Schema::create('salary_structure_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->decimal('basic_salary', 15, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('items')->comment('Snapshot of all allowances and deductions');
            $table->string('change_reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['salary_structure_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structure_versions');

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('salary_structure_snapshot');
        });

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
