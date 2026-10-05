<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'reference' => $this->reference,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'released_at' => $this->released_at?->toISOString(),
            'waybill_number' => $this->waybill_number,
            // Where the goods came from / went to (session 12).
            'warehouse_id' => $this->warehouse_id,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'discount_type' => $this->discount_type,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'balance_due' => (float) $this->balance_due,
            'total_refunded' => (float) $this->total_refunded,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentReceivedResource::collection($this->whenLoaded('payments')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
