<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones registered for push notifications (mobile plan A2). One row per
 * Firebase token; signing out of that API token deletes the row with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_devices')) {
            return;
        }
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique(); // sha256 of the token, which can be long
            $table->text('token');
            $table->string('platform', 10); // ios or android
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
