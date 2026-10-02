<?php

namespace App\Http\Resources;

use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Journal */
class JournalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_number' => $this->journal_number,
            'journal_date' => $this->journal_date?->format('Y-m-d'),
            'reverse_on' => $this->reverse_on?->format('Y-m-d'),
            'auto_reversal_journal_id' => $this->auto_reversal_journal_id,
            'reference' => $this->reference,
            'description' => $this->description,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'status' => $this->status,
            'is_posted' => $this->is_posted,
            'posted_at' => $this->posted_at?->toISOString(),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'entries' => JournalEntryResource::collection($this->whenLoaded('entries')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'approved_by' => new UserResource($this->whenLoaded('approvedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
