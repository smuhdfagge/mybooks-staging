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
        Schema::table('activity_logs', function (Blueprint $table) {
            // HMAC-SHA256 hash for tamper detection (64 hex chars)
            $table->string('integrity_hash', 64)->nullable()->after('description');
            // Chain hash from previous entry for ordering verification
            $table->string('previous_hash', 64)->nullable()->after('integrity_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['integrity_hash', 'previous_hash']);
        });
    }
};
