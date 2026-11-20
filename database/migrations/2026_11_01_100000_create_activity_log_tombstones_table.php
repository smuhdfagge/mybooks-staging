<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log is a hash chain: each entry carries the hash of the
 * entry before it. Erasing a closed business and the retention purge
 * delete entries on purpose, so logs:verify needs to know which links
 * point at an entry that was erased, not tampered with. A tombstone keeps
 * only the erased entry's id, its hash and why: none of its content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log_tombstones', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // the erased activity_logs.id
            $table->string('integrity_hash', 64)->index();
            $table->string('reason', 32);
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('integrity_hash');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['integrity_hash']);
        });
        Schema::dropIfExists('activity_log_tombstones');
    }
};
