<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_structures', function (Blueprint $table) {
            $table->string('name')->after('tenant_id');
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['tenant_id', 'employee_id']);
            $table->dropColumn('employee_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('salary_structure_id')->nullable()->after('salary_type')
                ->constrained('salary_structures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['salary_structure_id']);
            $table->dropColumn('salary_structure_id');
        });

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->foreignId('employee_id')->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'employee_id']);
            $table->dropColumn('name');
        });
    }
};
