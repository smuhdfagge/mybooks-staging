<?php

namespace App\Support;

use App\Models\DeliveryNote;
use App\Models\Tenant;

/**
 * Everything the delivery note document needs, in one place, so the
 * delivery note page, the print page and the PDF all show the same
 * document.
 *
 * A delivery note carries no money: it travels with the goods and comes
 * back signed, which is the proof the customer received them. So it shows
 * what was ordered against what was actually sent, and leaves room for
 * the signature.
 */
class DeliveryNoteDocument
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function data(DeliveryNote $note, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $note->tenant ?? Tenant::findOrFail($note->tenant_id);

        return DocumentBrand::of($tenant, $forPdf, $settings) + [
            'note' => $note,
            'state' => self::state($note),
            'short' => self::short($note),
        ];
    }

    /**
     * Where the delivery stands, in words.
     *
     * @return array{key: string, label: string}
     */
    public static function state(DeliveryNote $note): array
    {
        $key = match ($note->status) {
            DeliveryNote::STATUS_DELIVERED => 'delivered',
            DeliveryNote::STATUS_IN_TRANSIT => 'in_transit',
            DeliveryNote::STATUS_DISPATCHED => 'dispatched',
            DeliveryNote::STATUS_CANCELLED => 'cancelled',
            default => 'draft',
        };

        $label = [
            'delivered' => 'Delivered',
            'in_transit' => 'On the way',
            'dispatched' => 'Dispatched',
            'cancelled' => 'Cancelled',
            'draft' => 'Not sent yet',
        ][$key];

        return ['key' => $key, 'label' => $label];
    }

    /**
     * Whether anything is going out short of what was ordered. The driver
     * and the customer both need to see that before they sign.
     */
    public static function short(DeliveryNote $note): bool
    {
        return $note->items->contains(
            fn ($line) => (float) $line->quantity_ordered > 0
                && (float) $line->quantity_delivered < (float) $line->quantity_ordered
        );
    }

    public static function pdf(DeliveryNote $note): string
    {
        $note->loadMissing(['customer', 'items.item']);

        return DocumentBrand::pdf('delivery-notes.pdf', self::data($note, forPdf: true));
    }

    public static function fileName(DeliveryNote $note): string
    {
        return DocumentBrand::fileName('Delivery note', $note->delivery_number, $note->customer?->name);
    }
}
