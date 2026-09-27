<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_orders MODIFY COLUMN status ENUM('draft', 'confirmed', 'processing', 'invoiced', 'completed', 'cancelled') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_orders MODIFY COLUMN status ENUM('draft', 'confirmed', 'processing', 'completed', 'cancelled') DEFAULT 'draft'");
        }
    }
};
