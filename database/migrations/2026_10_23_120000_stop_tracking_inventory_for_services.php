<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Services do not hold stock, so they do not track inventory.
 *
 * The item form offered Type and "Track inventory for this item" as two
 * unrelated choices, and the tracking box was ticked by default. So a
 * service saved without unticking it was recorded as a stock item: it
 * appeared in the inventory list, counted towards the low stock tab, and
 * sent low stock reminders about consulting hours running out.
 *
 * The model now refuses to keep tracking on for a service. This settles
 * the rows written before that.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('items')
            ->where('type', 'service')
            ->where('track_inventory', true)
            ->update(['track_inventory' => false]);
    }

    public function down(): void
    {
        // Deliberately nothing. Going back would mean marking services as
        // stock-tracked again, which was the fault, and there is no record
        // of which rows were ticked by choice rather than by the default.
    }
};
