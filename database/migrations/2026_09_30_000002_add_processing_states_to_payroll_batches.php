<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finding N7: a queued batch is "processing" until the job finishes, and
 * "failed" (with the reason) if it doesn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved', 'processing', 'paid', 'failed', 'cancelled'])
                ->default('draft')->change();
            $table->text('failure_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });
    }
};
