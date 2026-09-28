<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fixed Asset Categories
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_useful_life', 8, 2)->nullable();
            $table->string('default_depreciation_method')->nullable();

            // Chart of Accounts with shorter foreign key names
            $table->foreignId('asset_account_id')->nullable()->constrained('chart_of_accounts', 'id', 'fac_asset_acc')->nullOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts', 'id', 'fac_accum_depr_acc')->nullOnDelete();
            $table->foreignId('depreciation_expense_account_id')->nullable()->constrained('chart_of_accounts', 'id', 'fac_depr_exp_acc')->nullOnDelete();
            $table->foreignId('gain_loss_account_id')->nullable()->constrained('chart_of_accounts', 'id', 'fac_gain_loss_acc')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        // Fixed Assets
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('fixed_asset_categories', 'id', 'fa_category')->nullOnDelete();
            $table->string('asset_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors', 'id', 'fa_vendor')->nullOnDelete();
            $table->string('purchase_invoice')->nullable();
            $table->date('purchase_date');
            $table->date('in_service_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->decimal('depreciable_amount', 15, 2);
            $table->decimal('useful_life', 8, 2); // In years
            $table->string('depreciation_method')->default('straight_line');
            $table->decimal('depreciation_rate', 8, 4)->nullable();
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2);
            $table->string('status')->default('active'); // active, under_maintenance, idle, disposed
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 15, 2)->nullable();
            $table->string('disposal_method')->nullable(); // sale, scrapped, donated, lost, other
            $table->text('disposal_notes')->nullable();
            $table->decimal('gain_loss_on_disposal', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'id', 'fa_assigned_user')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'fa_created_user')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'asset_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
        });

        // Fixed Asset Depreciation Records
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets', 'id', 'fad_asset')->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals', 'id', 'fad_journal')->nullOnDelete();
            $table->date('depreciation_date');
            $table->integer('period_number'); // Period number since in-service date
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('book_value', 15, 2);
            $table->string('status')->default('scheduled'); // scheduled, posted, reversed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'fad_created_user')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_number'], 'fad_asset_period_unique');
            $table->index(['tenant_id', 'depreciation_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
