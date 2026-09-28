<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Expense */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'name' => $this->name,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'amount' => (float) $this->amount,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'notes' => $this->notes,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'expense_account' => new ChartOfAccountResource($this->whenLoaded('expenseAccount')),
            'bank' => new BankResource($this->whenLoaded('bank')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'approved_by' => new UserResource($this->whenLoaded('approvedByUser')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
