<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name'); // e.g., "VAT", "GST", "Sales Tax", "WHT"
                $table->string('code', 20)->nullable(); // e.g., "VAT", "GST", "ST"
                $table->decimal('rate', 8, 4); // Tax rate percentage (e.g., 7.5000 for 7.5%)
                $table->enum('type', ['inclusive', 'exclusive'])->default('exclusive');
                $table->enum('applies_to', ['sales', 'purchases', 'both'])->default('both');
                $table->string('tax_number')->nullable(); // Company's tax registration number for this tax
                $table->text('description')->nullable();
                $table->boolean('is_compound')->default(false); // Applied on top of other taxes
                $table->boolean('is_default')->default(false); // Default tax for new items
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'is_active']);
                $table->index(['tenant_id', 'applies_to']);
            });
        }

        // Create tax groups for combining multiple taxes
        if (! Schema::hasTable('tax_groups')) {
            Schema::create('tax_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 20)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // Pivot table for tax groups
        if (! Schema::hasTable('tax_group_rates')) {
            Schema::create('tax_group_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tax_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tax_rate_id')->constrained()->cascadeOnDelete();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tax_group_id', 'tax_rate_id']);
            });
        }

        // Add tax configuration to items
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'tax_rate_id')) {
                $table->foreignId('tax_rate_id')->nullable()->after('reorder_level')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('items', 'tax_group_id')) {
                $table->foreignId('tax_group_id')->nullable()->after('tax_rate_id')->constrained()->nullOnDelete();
            }
            // is_taxable may already exist
            if (! Schema::hasColumn('items', 'is_taxable')) {
                $table->boolean('is_taxable')->default(true)->after('tax_group_id');
            }
        });

        // Add tax settings to tenant settings (for default behaviors)
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'default_sales_tax_id')) {
                $table->foreignId('default_sales_tax_id')->nullable()->after('settings')->constrained('tax_rates')->nullOnDelete();
            }
            if (! Schema::hasColumn('tenants', 'default_purchase_tax_id')) {
                $table->foreignId('default_purchase_tax_id')->nullable()->after('default_sales_tax_id')->constrained('tax_rates')->nullOnDelete();
            }
            if (! Schema::hasColumn('tenants', 'prices_include_tax')) {
                $table->boolean('prices_include_tax')->default(false)->after('default_purchase_tax_id');
            }
            if (! Schema::hasColumn('tenants', 'tax_per_line_item')) {
                $table->boolean('tax_per_line_item')->default(true)->after('prices_include_tax');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'default_sales_tax_id')) {
                $table->dropForeign(['default_sales_tax_id']);
                $table->dropColumn('default_sales_tax_id');
            }
            if (Schema::hasColumn('tenants', 'default_purchase_tax_id')) {
                $table->dropForeign(['default_purchase_tax_id']);
                $table->dropColumn('default_purchase_tax_id');
            }
            if (Schema::hasColumn('tenants', 'prices_include_tax')) {
                $table->dropColumn('prices_include_tax');
            }
            if (Schema::hasColumn('tenants', 'tax_per_line_item')) {
                $table->dropColumn('tax_per_line_item');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'tax_rate_id')) {
                $table->dropForeign(['tax_rate_id']);
                $table->dropColumn('tax_rate_id');
            }
            if (Schema::hasColumn('items', 'tax_group_id')) {
                $table->dropForeign(['tax_group_id']);
                $table->dropColumn('tax_group_id');
            }
        });

        Schema::dropIfExists('tax_group_rates');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('tax_rates');
    }
};
