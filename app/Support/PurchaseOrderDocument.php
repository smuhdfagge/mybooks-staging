<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\Tenant;

/**
 * Everything the purchase order document needs, in one place, so the
 * order page, the print page and the PDF all show the same document.
 *
 * A purchase order goes out to a supplier: it is the business's written
 * instruction to deliver and to invoice, and most suppliers will not move
 * without it. So it carries where the goods are to be delivered and a
 * signature authorising the spend.
 */
class PurchaseOrderDocument
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function data(PurchaseOrder $order, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $order->tenant ?? Tenant::findOrFail($order->tenant_id);
        $brand = DocumentBrand::of($tenant, $forPdf, $settings);

        return $brand + [
            'order' => $order,
            'state' => self::state($order),
            'showReceived' => self::showReceived($order),
            'inWords' => AmountInWords::format($order->total, $brand['currency']),
        ];
    }

    /**
     * Where the order stands, in words.
     *
     * @return array{key: string, label: string}
     */
    public static function state(PurchaseOrder $order): array
    {
        $key = match ($order->status) {
            'cancelled' => 'cancelled',
            'billed' => 'billed',
            'received' => 'received',
            'partially_received' => 'partially_received',
            'confirmed' => 'confirmed',
            default => 'draft',
        };

        $label = [
            'cancelled' => 'Cancelled',
            'billed' => 'Billed',
            'received' => 'All goods received',
            'partially_received' => 'Part received',
            'confirmed' => 'Ordered',
            'draft' => 'Draft',
        ][$key];

        return ['key' => $key, 'label' => $label];
    }

    /**
     * Whether to show what has arrived against what was ordered. Only once
     * something has, so the copy that goes to the supplier is a clean
     * order and the copy you print later doubles as a receiving check.
     */
    public static function showReceived(PurchaseOrder $order): bool
    {
        return in_array($order->status, ['partially_received', 'received', 'billed'], true)
            && $order->items->contains(fn ($line) => (float) $line->quantity_received > 0);
    }

    public static function pdf(PurchaseOrder $order): string
    {
        $order->loadMissing(['vendor', 'items.item']);

        return DocumentBrand::pdf('purchase-orders.pdf', self::data($order, forPdf: true));
    }

    public static function fileName(PurchaseOrder $order): string
    {
        return DocumentBrand::fileName('Purchase order', $order->order_number, $order->vendor?->name);
    }
}
