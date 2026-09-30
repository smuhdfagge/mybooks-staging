<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finding S2: platform admins get the same 2FA columns as users, and admin
 * actions are written to activity_logs with the admin's id.
 *
 * admin_user_id has no foreign key on purpose: the audit row must outlive
 * the admin account (the name and email are also kept in the row).
 * Admin remember-me tokens are cleared, since admins can no longer use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }
            if (! Schema::hasColumn('admin_users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable();
            }
            if (! Schema::hasColumn('admin_users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('admin_users', 'two_factor_last_used_at')) {
                $table->unsignedBigInteger('two_factor_last_used_at')->nullable();
            }
        });

        // Admin remember-me is gone; old remember-me cookies stop working.
        DB::table('admin_users')->update(['remember_token' => null]);

        if (! Schema::hasColumn('activity_logs', 'admin_user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_user_id')->nullable()->after('user_id');
                $table->index(['admin_user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'admin_user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropIndex(['admin_user_id', 'created_at']);
                $table->dropColumn('admin_user_id');
            });
        }

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_used_at']);
        });
    }
};
