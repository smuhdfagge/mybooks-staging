<?php

namespace App\Actions\DeliveryNotes;

use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating a delivery note for (part of) a sales order.
 *
 * Each line delivers some of an order line. A line can't deliver more than
 * is still outstanding on the order: ordered, less what earlier notes have
 * dispatched, less what other draft notes are about to deliver.
 *
 * Stock rule: a delivery note does not move stock. Stock leaves the books
 * when the invoice for the goods is released (InvoiceController::release,
 * which prints the waybill) or with a sales receipt; counting it here as
 * well would take the same goods out twice. The note is the shipping paper
 * and the record of what the customer has received.
 *
 * The note records the warehouse the goods are sent from (session 12;
 * default warehouse if none is chosen). It is printed on the note.
 *
 * $data keys: delivery_date, shipping_method, tracking_number,
 * shipping_address, notes, warehouse_id, lines[] (sales_order_item_id,
 * quantity).
 */
class SaveDeliveryNote
{
    /** Order statuses that can still have deliveries. */
    public const OPEN_ORDER_STATUSES = ['confirmed', 'processing', 'invoiced'];

    /** @param array<string, mixed> $data */
    public function create(SalesOrder $order, array $data, ?int $userId = null): DeliveryNote
    {
        if (! in_array($order->status, self::OPEN_ORDER_STATUSES, true)) {
            throw ValidationException::withMessages(['sales_order' => "Sales order {$order->order_number} is {$order->status}. Only confirmed orders can be delivered."]);
        }

        return DB::transaction(function () use ($order, $data, $userId) {
            $orderLines = SalesOrderItem::where('sales_order_id', $order->id)->lockForUpdate()->get()->keyBy('id');
            $outstanding = $this->outstanding($order, $orderLines);

            $lines = [];
            foreach ($data['lines'] ?? [] as $index => $line) {
                $quantity = round((float) ($line['quantity'] ?? 0), 2);
                if ($quantity <= 0) {
                    continue;
                }
                $orderLine = $orderLines->get((int) ($line['sales_order_item_id'] ?? 0));
                if (! $orderLine) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => 'That line is not on this sales order.']);
                }
                $left = $outstanding[$orderLine->id];
                if ($quantity - $left > 0.001) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => "Only {$this->qty($left)} of \"{$orderLine->description}\" is still to be delivered."]);
                }
                $lines[] = [$orderLine, $quantity];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => 'Enter the quantity delivered for at least one line.']);
            }

            $note = DeliveryNote::create([
                'tenant_id' => $order->tenant_id,
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'delivery_number' => DeliveryNote::generateNumber($order->tenant_id),
                'warehouse_id' => Warehouse::resolveIdFor($order->tenant_id, $data['warehouse_id'] ?? null, true),
                'delivery_date' => $data['delivery_date'],
                'status' => DeliveryNoteStatus::Draft->value,
                'shipping_method' => $data['shipping_method'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($lines as [$orderLine, $quantity]) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'sales_order_item_id' => $orderLine->id,
                    'item_id' => $orderLine->item_id,
                    'description' => $orderLine->description,
                    'quantity_ordered' => $orderLine->quantity,
                    'quantity_delivered' => $quantity,
                ]);
            }

            return $note->fresh(['items']);
        });
    }

    /**
     * What is still to be delivered on each order line: ordered, less
     * dispatched, less what draft notes already hold.
     *
     * @param  Collection<int, SalesOrderItem>|null  $orderLines
     * @return array<int, float> keyed by sales order item id
     */
    public function outstanding(SalesOrder $order, $orderLines = null): array
    {
        $orderLines ??= $order->items()->get()->keyBy('id');

        $held = DeliveryNoteItem::query()
            ->whereIn('sales_order_item_id', $orderLines->keys())
            ->whereHas('deliveryNote', fn ($q) => $q->where('status', DeliveryNoteStatus::Draft->value))
            ->selectRaw('sales_order_item_id, SUM(quantity_delivered) as qty')
            ->groupBy('sales_order_item_id')
            ->pluck('qty', 'sales_order_item_id');

        $out = [];
        foreach ($orderLines as $line) {
            $out[$line->id] = max(0, round((float) $line->quantity - (float) $line->quantity_fulfilled - (float) ($held[$line->id] ?? 0), 2));
        }

        return $out;
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
