<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Warehouses
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // 2. Inventory Layers (for FIFO/WAC stock valuation)
        Schema::create('inventory_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('remaining_quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->string('reference_type')->nullable(); // bill, adjustment, transfer, opening
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('received_date');
            $table->timestamps();

            $table->index(['tenant_id', 'item_id', 'warehouse_id'], 'inv_layers_tenant_item_wh_idx');
            $table->index(['tenant_id', 'item_id', 'remaining_quantity'], 'inv_layers_remaining_idx');
        });

        // 3. Stock Transfers
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number', 50);
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status', 20)->default('draft'); // draft, in_transit, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'transfer_number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Bill of Materials
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete(); // The assembly/finished product
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('output_quantity', 15, 4)->default(1); // How many finished products this BOM yields
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_materials_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete(); // Component item
            $table->decimal('quantity', 15, 4); // Quantity of component needed
            $table->decimal('waste_percentage', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Assembly Orders (build from BOM)
        Schema::create('assembly_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->foreignId('bill_of_materials_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4); // How many assemblies to build
            $table->string('status', 20)->default('draft'); // draft, in_progress, completed, cancelled
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
        });

        // 6. Serial Numbers & Batch/Lot Tracking
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 100);
            $table->decimal('quantity', 15, 4);
            $table->decimal('remaining_quantity', 15, 4);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'batch_number'], 'inv_batches_unique_idx');
            $table->index(['tenant_id', 'item_id', 'remaining_quantity'], 'inv_batches_remaining_idx');
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('serial_number', 100);
            $table->string('status', 20)->default('available'); // available, reserved, sold, returned, damaged
            $table->string('reference_type')->nullable(); // invoice, bill, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'serial_number'], 'serial_numbers_unique_idx');
            $table->index(['tenant_id', 'status']);
        });

        // 7. Unit of Measure
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Kilogram", "Piece", "Box", "Dozen"
            $table->string('abbreviation', 10); // e.g., "kg", "pc", "box", "dz"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'abbreviation']);
        });

        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete(); // null = global conversion
            $table->foreignId('from_uom_id')->constrained('unit_of_measures')->cascadeOnDelete();
            $table->foreignId('to_uom_id')->constrained('unit_of_measures')->cascadeOnDelete();
            $table->decimal('conversion_factor', 15, 6); // from_uom * factor = to_uom
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'from_uom_id', 'to_uom_id'], 'uom_conv_unique_idx');
        });

        // 8. Add valuation_method and tracking fields to items
        Schema::table('items', function (Blueprint $table) {
            $table->string('valuation_method', 20)->default('weighted_average')->after('track_inventory');
            // weighted_average, fifo
            $table->string('tracking_type', 20)->default('none')->after('valuation_method');
            // none, batch, serial
            $table->foreignId('purchase_uom_id')->nullable()->after('unit')->constrained('unit_of_measures')->nullOnDelete();
            $table->foreignId('sales_uom_id')->nullable()->after('purchase_uom_id')->constrained('unit_of_measures')->nullOnDelete();
        });

        // 9. Add warehouse reference to inventory table (was unused nullable field, now with FK)
        // warehouse_id already exists but without FK constraint - add it
        if (Schema::hasColumn('inventories', 'warehouse_id')) {
            // Column exists, just make sure downstream logic handles it
        } else {
            Schema::table('inventories', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('item_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_uom_id');
            $table->dropConstrainedForeignId('sales_uom_id');
            $table->dropColumn(['valuation_method', 'tracking_type']);
        });

        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('unit_of_measures');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('assembly_orders');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bill_of_materials');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_layers');
        Schema::dropIfExists('warehouses');
    }
};
