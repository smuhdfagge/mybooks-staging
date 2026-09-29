<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee IDs are numbered per business (EMP-00001, EMP-00002, ...) but the
 * column was unique across the whole database, so only the first business
 * could add an employee. Make it unique per business instead.
 *
 * Finding R1 (round 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add the new index first so tenant_id always has an index for its foreign key.
            $table->unique(['tenant_id', 'employee_id'], 'employees_tenant_employee_id_unique');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->dropIndex(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['tenant_id', 'employee_id']);
            $table->unique(['employee_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_tenant_employee_id_unique');
        });
    }
};
