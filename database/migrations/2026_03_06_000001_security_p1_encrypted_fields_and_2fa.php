<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security P1: Convert sensitive fields to TEXT for encrypted casts
 * and add two-factor authentication columns to users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Banks: encrypted fields need TEXT type ---
        Schema::table('banks', function (Blueprint $table) {
            $table->text('account_number')->nullable()->change();
            $table->text('routing_number')->nullable()->change();
            $table->text('swift_code')->nullable()->change();
            $table->text('iban')->nullable()->change();
        });

        // --- Employees: encrypted fields need TEXT type ---
        Schema::table('employees', function (Blueprint $table) {
            $table->text('salary')->nullable()->change();
            $table->text('bank_account_number')->nullable()->change();
            $table->text('bank_routing_number')->nullable()->change();
            $table->text('tax_id')->nullable()->change();
        });

        // --- Customers: encrypted tax_number ---
        Schema::table('customers', function (Blueprint $table) {
            $table->text('tax_number')->nullable()->change();
        });

        // --- Vendors: encrypted tax_number ---
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('tax_number')->nullable()->change();
        });

        // --- Users: Two-Factor Authentication ---
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('account_number')->nullable()->change();
            $table->string('routing_number')->nullable()->change();
            $table->string('swift_code')->nullable()->change();
            $table->string('iban')->nullable()->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('salary', 15, 2)->nullable()->change();
            $table->string('bank_account_number')->nullable()->change();
            $table->string('bank_routing_number')->nullable()->change();
            $table->string('tax_id')->nullable()->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->change();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
