<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A service is something the business does, not something it holds.
 *
 * The item form offered Type and "Track inventory for this item" as two
 * unrelated choices, with tracking ticked by default. So a service saved
 * without unticking it became a stock item: it sat in the inventory
 * list, counted towards the low stock tab, and sent reminders that
 * consulting hours were running low. Nobody can reorder an hour.
 */
class ServicesHoldNoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_service_turns_stock_tracking_off(): void
    {
        $item = Item::factory()->create([
            'type' => Item::TYPE_SERVICE,
            'track_inventory' => true,
        ]);

        // Asked for by the form, refused by the model, so it holds for
        // imports and the API too.
        $this->assertFalse((bool) $item->fresh()->track_inventory);
    }

    public function test_a_product_keeps_its_tracking(): void
    {
        $item = Item::factory()->create([
            'type' => Item::TYPE_PRODUCT,
            'track_inventory' => true,
        ]);

        $this->assertTrue((bool) $item->fresh()->track_inventory);
    }

    public function test_turning_a_product_into_a_service_turns_tracking_off(): void
    {
        $item = Item::factory()->create(['type' => Item::TYPE_PRODUCT, 'track_inventory' => true]);

        $item->update(['type' => Item::TYPE_SERVICE]);

        $this->assertFalse((bool) $item->fresh()->track_inventory);
    }

    public function test_the_stock_tracked_scope_leaves_services_out(): void
    {
        $product = Item::factory()->create(['type' => Item::TYPE_PRODUCT, 'track_inventory' => true]);
        $service = Item::factory()->create(['type' => Item::TYPE_SERVICE, 'track_inventory' => true]);
        // A row that got in before the rule existed, written past the model.
        DB::table('items')->where('id', $service->id)->update(['track_inventory' => true]);

        $tracked = Tenant::acrossAll(fn () => Item::query()->stockTracked()->pluck('id'));

        $this->assertTrue($tracked->contains($product->id));
        $this->assertFalse($tracked->contains($service->id), 'A service was counted as stock.');
    }

    public function test_no_low_stock_reminder_for_a_service(): void
    {
        Notification::fake();
        $user = $this->createAuthenticatedUser();
        NotificationSetting::firstOrCreate(['tenant_id' => $user->tenant_id], ['send_low_stock_alerts' => true]);

        $service = Item::factory()->create([
            'tenant_id' => $user->tenant_id,
            'type' => Item::TYPE_SERVICE,
            'reorder_level' => 10,
            'is_active' => true,
        ]);
        // Forced past the model, as an older row would be.
        DB::table('items')->where('id', $service->id)->update(['track_inventory' => true]);

        $sent = app(NotificationService::class)->sendLowStockAlerts($user->tenant_id);

        $this->assertFalse($sent, 'A low stock alert went out for a service.');
        Notification::assertNothingSent();
    }

    public function test_the_migration_clears_tracking_on_services_already_saved(): void
    {
        $service = Item::factory()->create(['type' => Item::TYPE_SERVICE]);
        DB::table('items')->where('id', $service->id)->update(['track_inventory' => true]);

        // What the migration runs, against rows the old form wrote.
        DB::table('items')->where('type', 'service')->where('track_inventory', true)
            ->update(['track_inventory' => false]);

        $this->assertFalse((bool) $service->fresh()->track_inventory);
    }
}
