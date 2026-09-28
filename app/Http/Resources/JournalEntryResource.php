<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\JournalEntry */
class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
            'description' => $this->description,
            'account' => new ChartOfAccountResource($this->whenLoaded('account')),
        ];
    }
}
