<?php

namespace App\Http\Resources;

use App\Models\PaymentMade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMade */
class PaymentMadeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'amount' => (float) $this->amount,
            // Withholding tax: amount is the money paid; amount + wht_amount settles the bill.
            'wht_category_id' => $this->wht_category_id,
            'wht_rate' => (float) $this->wht_rate,
            'wht_base' => (float) $this->wht_base,
            'wht_amount' => (float) $this->wht_amount,
            'wht_authority' => $this->wht_authority,
            'wht_state' => $this->wht_state,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'bill' => new BillResource($this->whenLoaded('bill')),
            'bank' => new BankResource($this->whenLoaded('bank')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
