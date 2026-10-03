<?php

namespace App\Http\Resources;

use App\Models\PaymentReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentReceived */
class PaymentReceivedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'is_deposit' => $this->is_deposit,
            // Withholding tax the customer deducted: amount + wht_amount settles the invoice.
            'wht_category_id' => $this->wht_category_id,
            'wht_rate' => (float) $this->wht_rate,
            'wht_base' => (float) $this->wht_base,
            'wht_amount' => (float) $this->wht_amount,
            'wht_status' => $this->whtStatus(),
            'wht_credit_note_number' => $this->wht_credit_note_number,
            'wht_credit_note_date' => $this->wht_credit_note_date?->format('Y-m-d'),
            'unused_amount' => (float) $this->unused_amount,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'bank' => new BankResource($this->whenLoaded('bank')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
