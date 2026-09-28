<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Collection|array $items
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $itemCount = count($this->items);

        $message = (new MailMessage)
            ->subject("Low Stock Alert: {$itemCount} item(s) need attention")
            ->greeting("Hello {$notifiable->name},")
            ->line('The following items are running low on stock and may need to be reordered:');

        foreach ($this->items as $item) {
            $currentStock = $item->inventory?->quantity ?? 0;
            $message->line("• **{$item->name}** (SKU: {$item->sku}) - Current: {$currentStock}, Reorder Level: {$item->reorder_level}");
        }

        return $message
            ->action('View Inventory', route('inventory.index'))
            ->line('Please review and place orders as needed.');
    }

    public function toArray(object $notifiable): array
    {
        $itemIds = collect($this->items)->pluck('id')->toArray();

        return [
            'type' => 'low_stock',
            'item_count' => count($this->items),
            'item_ids' => $itemIds,
            'message' => count($this->items).' item(s) are low on stock',
        ];
    }
}
