<?php

use App\Models\WhtCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax (WHT) setup: each business's editable table of WHT
 * transaction types and rates (seeded with the statutory rates, see
 * WhtCategory::defaults()), plus WHT flags on vendors and customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wht_categories')) {
            Schema::create('wht_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('rate_company', 5, 2)->default(0);
                $table->decimal('rate_individual', 5, 2)->default(0);
                $table->boolean('double_without_tin')->default(true);
                $table->date('effective_from')->nullable();
                $table->string('source')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        if (! Schema::hasColumn('vendors', 'payee_type')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('payee_type', 20)->default('company');
                $table->foreignId('wht_category_id')->nullable()->constrained('wht_categories')->nullOnDelete();
                $table->boolean('wht_exempt')->default(false);
            });
        }
        if (! Schema::hasColumn('customers', 'payee_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('payee_type', 20)->default('company');
                $table->foreignId('wht_category_id')->nullable()->constrained('wht_categories')->nullOnDelete();
                $table->boolean('wht_exempt')->default(false);
            });
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            WhtCategory::seedDefaults((int) $tenantId);
        }
    }

    public function down(): void
    {
        foreach (['vendors', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'wht_category_id')) {
                    $t->dropConstrainedForeignId('wht_category_id');
                }
                foreach (['payee_type', 'wht_exempt'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('wht_categories');
    }
};
