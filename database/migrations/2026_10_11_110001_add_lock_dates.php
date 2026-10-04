<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lock dates (session 11): two dates per business. Nothing dated on or
 * before the staff lock date can be changed except by users allowed to
 * override it (admins, accountants); nothing dated on or before the
 * all-users lock date can be changed by anyone. Every change to a lock
 * date, every period closed or reopened and every VAT return reopened is
 * kept in lock_date_changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'staff_lock_date')) {
                $table->date('staff_lock_date')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'all_users_lock_date')) {
                $table->date('all_users_lock_date')->nullable();
            }
        });

        if (! Schema::hasTable('lock_date_changes')) {
            Schema::create('lock_date_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 20); // staff, all_users, period, vat_return ("lock" is a reserved word in MySQL)
                $table->date('old_date')->nullable();
                $table->date('new_date')->nullable();
                $table->text('reason')->nullable();
                $table->string('description')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lock_date_changes');

        Schema::table('tenants', function (Blueprint $table) {
            foreach (['staff_lock_date', 'all_users_lock_date'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
