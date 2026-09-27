<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft deletes to financial record tables that were missing them.
     * These are critical financial documents that should never be permanently deleted.
     */
    public function up(): void
    {
        Schema::table('payments_received', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payments_made', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('payments_received', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payments_made', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
