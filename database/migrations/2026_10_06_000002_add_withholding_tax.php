<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Withholding tax, "deduction at source" (tax pack 2).
 *
 * - wht_rates: the business's rates by type of transaction, company and
 *   individual (defaults are added the first time a business uses WHT).
 * - customers and vendors: company or individual (their TIN is the
 *   existing tax_number field).
 * - payments_made / payments_received: WHT taken off the payment.
 * - wht_credits: WHT customers took off what they paid us, and the
 *   certificate (credit note) they send for it.
 * - WHT Payable (2420) and WHT Receivable (1420) accounts.
 * - Permissions: view / manage withholding-tax.
 */
return new class extends Migration
{
    private array $accounts = [
        ['account_code' => '1420', 'name' => 'WHT Receivable (tax credits)', 'type' => 'asset', 'sub_type' => 'other_current_asset'],
        ['account_code' => '2420', 'name' => 'WHT Payable', 'type' => 'liability', 'sub_type' => 'other_current_liability'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('wht_rates')) {
            Schema::create('wht_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->decimal('rate_company', 5, 2)->nullable(); // null: does not apply to companies
                $table->decimal('rate_individual', 5, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        if (! Schema::hasColumn('customers', 'entity_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('entity_type', 20)->nullable(); // company or individual
            });
        }
        if (! Schema::hasColumn('vendors', 'entity_type')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('entity_type', 20)->nullable(); // company or individual
            });
        }

        if (! Schema::hasColumn('payments_made', 'wht_amount')) {
            Schema::table('payments_made', function (Blueprint $table) {
                $table->foreignId('wht_rate_id')->nullable()->constrained('wht_rates')->nullOnDelete();
                $table->decimal('wht_rate', 5, 2)->default(0);
                $table->decimal('wht_amount', 15, 2)->default(0);
            });
        }
        if (! Schema::hasColumn('payments_received', 'wht_amount')) {
            Schema::table('payments_received', function (Blueprint $table) {
                $table->foreignId('wht_rate_id')->nullable()->constrained('wht_rates')->nullOnDelete();
                $table->decimal('wht_rate', 5, 2)->default(0);
                $table->decimal('wht_amount', 15, 2)->default(0);
            });
        }

        if (! Schema::hasTable('wht_credits')) {
            Schema::create('wht_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payment_received_id')->nullable()->constrained('payments_received')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('deducted_on');
                $table->string('status', 20)->default('awaiting'); // awaiting, received
                $table->string('certificate_number', 100)->nullable();
                $table->date('certificate_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($this->accounts as $account) {
                $exists = DB::table('chart_of_accounts')
                    ->where('tenant_id', $tenantId)
                    ->where('account_code', $account['account_code'])
                    ->exists();
                if (! $exists) {
                    DB::table('chart_of_accounts')->insert($account + [
                        'tenant_id' => $tenantId,
                        'is_system' => true,
                        'is_active' => true,
                        'current_balance' => 0,
                        'opening_balance' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Permission::firstOrCreate(['name' => 'view withholding-tax', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage withholding-tax', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Admins get both; roles that see reports can view, roles that post
        // journals can manage (including businesses' own copies of roles).
        Role::query()->with('permissions')->each(function (Role $role) {
            $grant = [];
            if (in_array($role->name, ['super-admin', 'admin', 'accountant'], true) || $role->permissions->contains('name', 'view reports')) {
                $grant[] = 'view withholding-tax';
            }
            if (in_array($role->name, ['super-admin', 'admin', 'accountant'], true) || $role->permissions->contains('name', 'create journals')) {
                $grant[] = 'manage withholding-tax';
            }
            if ($grant) {
                $role->givePermissionTo($grant);
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('wht_credits');
        foreach (['payments_made', 'payments_received'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('wht_rate_id');
                $table->dropColumn(['wht_rate', 'wht_amount']);
            });
        }
        foreach (['customers', 'vendors'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('entity_type'));
        }
        Schema::dropIfExists('wht_rates');
        Permission::whereIn('name', ['view withholding-tax', 'manage withholding-tax'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
